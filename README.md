# RateLimitControl
A functional and simple rate limit control to prevent request attacks ready-to-use for PHP.

### Features:
- Prepared statements (using PDO)
- Working under a customizable unique identifier
- It works under database and no drivers or cache management

### Requirements:
- PHP > 7.3
- MySQL

### Installation:
- Import the .sql files found in the SQL Files folder to your database
- Download the rate.limit.php file and simply include it in the code where you will use it

#### Example of use

```php

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
```

### Small note:
Sorry for the comments and code written in Spanish, it was easier for me to understand the process when creating the code, anyway it is not very difficult to understand

### Contributions, reports or suggestions
If you find a problem or have a suggestion inside this library, please let me know by [clicking here](https://github.com/biitez/RateLimitControl/issues), if you want to improve the code, make it cleaner or even more secure, create a [pull request](https://github.com/biitez/RateLimitControl/pulls). 

### Credits

- `Telegram: https://t.me/biitez`
- `Bitcoin Addy: bc1qzz4rghmt6zg0wl6shzaekd59af5znqhr3nxmms`
