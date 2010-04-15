JS Architecture for Tap
=======================

The Scripts and MooTools
------------------------

All of Tap's scripts reside in /views/scripts. We use MooTools-Core and a "MooTools-More", which is actually not really -more but a special set of extensions:

- Delegation (from official -More)
- Template.js (Mark Obcena)
- Observer.js (Mark Obcena)
- Validator.js (Mark Obcena)
- TextOverlay.js (Garrick Cheung)
- Fx.Slide (from official -More)
- Native Extensions (various)


Special MooTools Extensions
---------------------------

- Element.getData/setData: Used for accessing data-attributes from elements.
- Function.toHandler: Used as a replacement for Function.bind for event handlers; turns the event handler function's signature into fn(element, event).
- String.cleanup: Used for templates to cleanup the string template (because browsers tend to bork them).
- String.remove: Shortcut for doing String.replace(regexp, '');
- Date.format: Used to format the date using the php date function format. Not from -more.


General PubSub Overview
-----------------------

The bulk of the code is written in a Observer+Pub/Sub style. A Observer+Pub/Sub system works using a base observer object that controls all publisher-subscribers and enables communication between them using "events." This object is the `_tap` object.

A "module" is an object that is registered via the observer (using `_tap.register(obj)`) and is turned into a publisher-subscriber (pub-sub obj). A pub-sub object can listen to other pub-sub objects by "subscribing" to their events, and it can also tell other pub-sub objects what to do by "publishing" events. All pub-sub objects have a `publish(eventname, data)` and a `subscribe({eventname: fnhandler})` method.

Everything is decoupled: no pub-sub object should know about other pub-sub objects. They should never directly call another object's methods but instead they should listen for published events. This makes it possible to reuse the same module in different parts of the app without modification.

A general rule is that a pub-sub object could subscribe to different types of events, but could only publish one type (except for a few cases). This centralizes the code so that you'll be able to trace which object is publishing the events. The convention is to use dot notation for event names: 'list.item', 'list.removed', 'feed.load', and 'feed.updated' refer to two types of events, 'list' events and 'feed' events.