.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Requirements
^^^^^^^^^^^^

This extension requires the Python interpreter to be available on your web server.

If you plan to build PDF, you will need additionally commands "make" and "pdflatex".

And of course, you need a documentation written in reStructuredText and as a Sphinx project. Please visit http://wiki.typo3.org/ReST for further information.

The extension supports single directory projects:

	| /path/to/project/
	| ├── _build
	| ├── conf.py
	| └── *other files*

separate source/build directory projects:

	| /path/to/project/
	| ├── build
	| └── source
	|     ├── conf.py
	|     └── *other files*

and TYPO3 documentation directory structure:

	| /path/to/project/
	| ├── *other files*
	| └── _make
	|     ├── build
	|     └── conf.py