HTML Architecture for Tap
=========================

All of Tap's templates reside in the /views directory, ending with .phtml, and there's a direct relationship between a pages and a view. The templates are written in HTML5 and use several features of the new markup edition.

A page is divided into several parts:

- "#header" contains the logo, the tap navigation and the global search field;
- "#footer" contains the copyright text;
- "#templates" is a hidden div used for client-side templating; and
- "#main" is the main area, subdivided into two parts:
	- "#sidebar" contains the page-specific navigation and controls
	- "#content" contains the page-specific widgets and data


Passing Data from the Server
----------------------------

Server-side templating is used to fill the templates with initial data. If you need to pass data from the server to the client side, you have two options:

1. If the data you are passing is global (eg, a list of all groups, the uid of the user, etc), create a script element with a single declaration `window._vars = {}` and fill the object with key-value pairs; or
2. If the data you are passing is specific to an item, append a data-attribute to the element in the template where the item will be created (eg, in the `li` element within the tapstream, for a tap, etc).

You can check out /views/new_homepage for an example of both usages.


The Templates Area
------------------

We use a client-side templating system in order to turn the data passed by the server into proper html elements. The templating engine relies on a string template that will be parsed. These templates are stored directly on the pages as real elements inside the `#templates` div.

All pages have access to a `_template` object that could be used to process these templates. You can check out /views/new_homepage for an example of client-side templates.