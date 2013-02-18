## Console_app

This class can be used to parse and retrieve the values of arguments passed to PHP applications started from a command line shell or DOS.

It can:

* Parse arguments and extract option values according to a definition of the option switches.
* Generate the help screen to explain the options to the users
* Display formatted messages to the console with ASCII control sequences to set color, bold, underline, reverse and blink styles.
* Read a input parameters typed by the user in the console.
* Reconfigure configuration files interactively
* draw table and progression bar This class does not require any specific PHP extension.

## PHPInteractive
You will also find an interactive shell using console_app in this repository named PHPinteractive that will help you execute php by entering it interactively on the command line. This is not so usefull nowadays but back at the time console_app was written it was a must have + it have some nice features:

* allow to pass includes files as parameter
* flag for setting error_reporting to E_ALL 
* history management
* flag to disable the **>** prompt
* possibility to save the script to a file at exit time
