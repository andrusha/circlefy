# @author Donovan Preston
#
# Copyright (c) 2006-2007, Linden Research, Inc.
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

from unittest import TestCase, main

from eventlet import api, timer

class TestTimer(TestCase):
    mode = 'static'

    def test_copy(self):
        t = timer.Timer(0, lambda: None)
        t2 = t.copy()
        assert t.seconds == t2.seconds
        assert t.tpl == t2.tpl
        assert t.called == t2.called

##     def test_cancel(self):
##         r = runloop.RunLoop()
##         called = []
##         t = timer.Timer(0, lambda: called.append(True))
##         t.cancel()
##         r.add_timer(t)
##         r.add_observer(lambda r, activity: r.abort(), 'after_waiting')
##         r.run()
##         assert not called
##         assert not r.running

    def test_schedule(self):
        hub = api.get_hub()
        # clean up the runloop, preventing side effects from previous tests
        # on this thread
        if hub.running:
            hub.abort()
            api.sleep(0)
        called = []
        #t = timer.Timer(0, lambda: (called.append(True), hub.abort()))
        #t.schedule()
        # let's have a timer somewhere in the future; make sure abort() still works
        # (for libevent, its dispatcher() does not exit if there is something scheduled)
        # XXX libevent handles this, other hubs do not
        #api.get_hub().schedule_call_global(10000, lambda: (called.append(True), hub.abort()))
        api.get_hub().schedule_call_global(0, lambda: (called.append(True), hub.abort()))
        hub.default_sleep = lambda: 0.0
        hub.switch()
        assert called
        assert not hub.running

if __name__ == '__main__':
    main()
