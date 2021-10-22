<?php

require_once('rate.limit.php');

$myPDO = new PDO('mysql:host=localhost;dbname=your_db_name', 'user', 'password');

$RateLimitAdapter = new RateLimit(
    Controller: "MyRateLimit", # The controller name of this rate limit
    UniqIdenfier: "123-123-123", # Here you can enter the user's IP or in case it is after login, a token or user id (Recommended a IP Address)
    MaxAttempsEach20Minutes: 10, # Maximum attempts the user must make in 20 minutes to be limited
    LimitationTimeOnMinutes: 15, # The time that the user will be limited in MINUTES, 15 = 15 minutes
    pdoConnection: $myPDO); # The connection to the database must be PDO
       
$CheckRateLimit = $RateLimitAdapter->CheckLimit();

# If the return of the function is NOT a boolean, it means that the user is limited
if (!is_bool($CheckRateLimit)) {

  $MinsToBeDelimited = $CheckRateLimit->i;
  $SecsToBeDelimited = $CheckRateLimit->s;
  
  echo 'Too many requests!, please wait: ' . $MinsToBeDelimited . ' minutes and '. $SecsToBeDelimited . ' seconds.';
  
} else {

  # Here you can proceed with your code ...
}
