Bolt Auditing Extension
=======================

Logging extension for Bolt access control audit events.

### Logging Target

There are currently two choices for logging targets, a database table or the
operating system log (syslog). One, or both, can be selected in the 
configuration.


### Event Selection

There are three categories of events:
  * Access control checks
  * Login/logout
  * Password resets

All three can be logged on success or failure, and checks and resets can
be also configured to log on request.

**Note:** Access control request logging should generally only be used when
debugging as it will trigger on every request to a back end zone.
