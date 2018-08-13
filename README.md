# AntiSpam Module
[![Build Status](https://scrutinizer-ci.com/g/WildPHP/module-antispam/badges/build.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-antispam/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/WildPHP/module-antispam/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-antispam/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/wildphp/module-antispam/v/stable)](https://packagist.org/packages/wildphp/module-antispam)
[![Latest Unstable Version](https://poser.pugx.org/wildphp/module-antispam/v/unstable)](https://packagist.org/packages/wildphp/module-antispam)
[![Total Downloads](https://poser.pugx.org/wildphp/module-antispam/downloads)](https://packagist.org/packages/wildphp/module-antispam)

Automatically kick users when a message pattern is matched.

## System Requirements
If your setup can run the main bot, it can run this module as well.

## Installation
To install this module, we will use `composer`:

```composer require wildphp/module-antispam```

That will install all required files for the module. In order to activate the module, add the following line to your modules array in `config.neon`:

    - WildPHP\Modules\AntiSpam\AntiSpam

The bot will run the module the next time it is started.

## Usage
The AntiSpam module relies on a blacklist to do its work. To manipulate the blacklist, the following commands are available.
All patterns must be given as valid regular expressions. See [the php manual on PCRE syntax](https://secure.php.net/manual/en/reference.pcre.pattern.syntax.php) for more details.

In addition to a blacklist the module maintains a list of exempted *nicknames*. These nicknames are exempted from checks and will not be kicked even if their message contains a blacklisted pattern.

If the bot is not a channel OP, it will still detect the spam and give a single notice per nickname in the channel asking users to inform channel OPs. 

* `blacklist [pattern]`
    * Required permission: `sa_blacklist`
* `unblacklist [pattern]`
    * Required permission: `sa_blacklist`
* `exempt [nickname]`
    * Required permission: `sa_exempt`
* `unexempt [nickname]`
    * Required permission: `sa_exempt`

### Use case: Blocking messages with multiple consecutive spaces
Since the bot trims given arguments (removes extra whitespace), it is not as straightforward to blacklist a pattern of multiple consecutive spaces.

**Do not blacklist messages with consecutive spaces by giving the spaces as argument to `blacklist`**. 
This will cause the bot to block every message containing a space, making it virtually impossible to undo the blacklist if you are not exempted.

Instead, the following command illustrates the best solution for this problem:

* `blacklist [ ]{8}` where 8 is the number of spaces that need to be matched.

## License
This module is licensed under the MIT license. Please see `LICENSE` to read it.
