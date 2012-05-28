Here is an xmlrpcConnector class for connecting to the xmlrpc server of the Services module.
It is not using Drupal methods, making it usable from any non-Drupal websites as well.
It is using the XML-RPC for php library (version 3 beta) http://phpxmlrpc.sourceforge.net to download.

Change the require _once path at line 16 if you put the library in a different place (I put it just next to my file).
Don't forget to generate a key and set up the appropriate permissions for the methods you want to use.
I only coded methods for user manipulation so far, feel free to implement additional functionalities. The good thing is that it provides base functions that you can reuse to easily invoke Services methods.
