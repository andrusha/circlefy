# Copyright (c) 2007, Linden Research, Inc.
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
# THE SOFTWARE.

import bisect
import sys
import traceback
import time

from eventlet.support import greenlets as greenlet
from eventlet.timer import Timer, LocalTimer

_g_debug = True

class BaseHub(object):
    """ Base hub class for easing the implementation of subclasses that are
    specific to a particular underlying event architecture. """

    SYSTEM_EXCEPTIONS = (KeyboardInterrupt, SystemExit)

    def __init__(self, clock=time.time):
        self.readers = {}
        self.writers = {}
        self.excs = {}

        self.clock = clock
        self.greenlet = greenlet.greenlet(self.run)
        self.stopping = False
        self.running = False
        self.timers = []
        self.next_timers = []
        self.observers = {}
        self.observer_modes = {
            'entry': [],
            'before_timers': [],
            'before_waiting': [],
            'after_waiting': [],
            'exit': [],
        }

    def add_descriptor(self, fileno, read=None, write=None, exc=None):
        """ Signals an intent to read/write from a particular file descriptor.

        The fileno argument is the file number of the file of interest.  The other
        arguments are either callbacks or None.  If there is a callback for read
        or write, the hub sets things up so that when the file descriptor is
        ready to be read or written, the callback is called.

        The exc callback is called when the socket represented by the file
        descriptor is closed.  The intent is that the the exc callbacks should
        only be present when either a read or write callback is also present,
        so the exc callback happens instead of the respective read or write
        callback.
        """
        read = read or self.readers.get(fileno)
        if read is not None:
            self.readers[fileno] = read
        else:
            self.readers.pop(fileno, None)
        write = write or self.writers.get(fileno)
        if write is not None:
            self.writers[fileno] = write
        else:
            self.writers.pop(fileno, None)
        exc = exc or self.excs.get(fileno)
        if exc is not None:
            self.excs[fileno] = exc
        else:
            self.excs.pop(fileno, None)
        return fileno

    def remove_descriptor(self, fileno):
        self.readers.pop(fileno, None)
        self.writers.pop(fileno, None)
        self.excs.pop(fileno, None)

    def exc_descriptor(self, fileno):
        exc = self.excs.get(fileno)
        if exc is not None:
            try:
                exc(fileno)
            except self.SYSTEM_EXCEPTIONS:
                self.squelch_exception(fileno, sys.exc_info())

    def stop(self):
        self.abort()
        if self.greenlet is not greenlet.getcurrent():
            self.switch()

    def switch(self):
        cur = greenlet.getcurrent()
        assert cur is not self.greenlet, 'Cannot switch to MAINLOOP from MAINLOOP'
        switch_out = getattr(cur, 'switch_out', None)
        if switch_out is not None:
            try:
                switch_out()
            except:
                traceback.print_exception(*sys.exc_info())
        if self.greenlet.dead:
            self.greenlet = greenlet.greenlet(self.run)
        try:
            greenlet.getcurrent().parent = self.greenlet
        except ValueError:
            pass
        return self.greenlet.switch()

    def squelch_exception(self, fileno, exc_info):
        traceback.print_exception(*exc_info)
        print >>sys.stderr, "Removing descriptor: %r" % (fileno,)
        try:
            self.remove_descriptor(fileno)
        except Exception, e:
            print >>sys.stderr, "Exception while removing descriptor! %r" % (e,)

    def wait(self, seconds=None):
        raise NotImplementedError("Implement this in a subclass")

    def default_sleep(self):
        return 60.0

    def sleep_until(self):
        t = self.timers
        if not t:
            return None
        return t[0][0]

    def run(self):
        """Run the runloop until abort is called.
        """
        if self.running:
            raise RuntimeError("Already running!")
        try:
            self.running = True
            self.stopping = False
            self.fire_observers('entry')
            while not self.stopping:
                self.prepare_timers()
                self.fire_observers('before_timers')
                self.fire_timers(self.clock())
                self.prepare_timers()
                wakeup_when = self.sleep_until()
                if wakeup_when is None:
                    sleep_time = self.default_sleep()
                else:
                    sleep_time = wakeup_when - self.clock()
                if sleep_time > 0:
                    self.fire_observers('before_waiting')
                    self.wait(sleep_time)
                    self.fire_observers('after_waiting')
                else:
                    self.wait(0)
            else:
                del self.timers[:]
                del self.next_timers[:]
            self.fire_observers('exit')
        finally:
            self.running = False
            self.stopping = False

    def abort(self):
        """Stop the runloop. If run is executing, it will exit after completing
        the next runloop iteration.
        """
        if self.running:
            self.stopping = True

    def add_observer(self, observer, *modes):
        """Add an event observer to this runloop with the given modes.
        Valid modes are:
            entry: The runloop is being entered.
            before_timers: Before the expired timers for this iteration are executed.
            before_waiting: Before waiting for the calculated wait_time
                where nothing will happen.
            after_waiting: After waiting, immediately before starting the top of the
                runloop again.
            exit: The runloop is exiting.

        If no mode is passed or mode is all, the observer will be fired for every
        event type.
        """
        if not modes or modes == ('all',):
            modes = tuple(self.observer_modes)
        self.observers[observer] = modes
        for mode in modes:
            self.observer_modes[mode].append(observer)

    def remove_observer(self, observer):
        """Remove a previously registered observer from all event types.
        """
        for mode in self.observers.pop(observer, ()):
            self.observer_modes[mode].remove(observer)

    def squelch_observer_exception(self, observer, exc_info):
        traceback.print_exception(*exc_info)
        print >>sys.stderr, "Removing observer: %r" % (observer,)
        self.remove_observer(observer)

    def fire_observers(self, activity):
        for observer in self.observer_modes[activity]:
            try:
                observer(self, activity)
            except self.SYSTEM_EXCEPTIONS:
                raise
            except:
                self.squelch_observer_exception(observer, sys.exc_info())

    def squelch_timer_exception(self, timer, exc_info):
        traceback.print_exception(*exc_info)
        print >>sys.stderr, "Timer raised: %r" % (timer,)

    def _add_absolute_timer(self, when, info):
        # the 0 placeholder makes it easy to bisect_right using (now, 1)
        self.next_timers.append((when, 0, info))

    def add_timer(self, timer):
        scheduled_time = self.clock() + timer.seconds
        self._add_absolute_timer(scheduled_time, timer)
        return scheduled_time

    def timer_finished(self, timer):
        pass

    def timer_canceled(self, timer):
        self.timer_finished(timer)

    def prepare_timers(self):
        ins = bisect.insort_right
        t = self.timers
        for item in self.next_timers:
            ins(t, item)
        del self.next_timers[:]

    def schedule_call_local(self, seconds, cb, *args, **kw):
        """Schedule a callable to be called after 'seconds' seconds have
        elapsed. Cancel the timer if greenlet has exited.
            seconds: The number of seconds to wait.
            cb: The callable to call after the given time.
            *args: Arguments to pass to the callable when called.
            **kw: Keyword arguments to pass to the callable when called.
        """
        t = LocalTimer(seconds, cb, *args, **kw)
        self.add_timer(t)
        return t

    schedule_call = schedule_call_local

    def schedule_call_global(self, seconds, cb, *args, **kw):
        """Schedule a callable to be called after 'seconds' seconds have
        elapsed. The timer will NOT be cancelled if the current greenlet has
        exited before the timer fires.
            seconds: The number of seconds to wait.
            cb: The callable to call after the given time.
            *args: Arguments to pass to the callable when called.
            **kw: Keyword arguments to pass to the callable when called.
        """
        t = Timer(seconds, cb, *args, **kw)
        self.add_timer(t)
        return t

    def fire_timers(self, when):
        t = self.timers
        last = bisect.bisect_right(t, (when, 1))
        i = 0
        for i in xrange(last):
            timer = t[i][2]
            try:
                try:
                    timer()
                except self.SYSTEM_EXCEPTIONS:
                    raise
                except:
                    self.squelch_timer_exception(timer, sys.exc_info())
            finally:
                self.timer_finished(timer)
        del t[:last]

    # for debugging:

    def get_readers(self):
        return self.readers

    def get_writers(self):
        return self.writers

    def get_excs(self):
        return self.excs

    def get_timers_count(hub):
        return max(len(x) for x in [hub.timers, hub.next_timers])

