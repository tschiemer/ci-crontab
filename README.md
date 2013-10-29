ci-crontab
==========

Crontab manager and CLI helper (intended) for CodeIgniter

# Features

* Directly interacts with system level crontab to maintain cronjobs.
* Differentiates between cronjobs managed by itself and external cronjobs such as not to interfere with these.
* Identifies any cronjob with an ID, which allows any caller to uniquely add/remove it.
* Allows `once` cronjobs, that run only .. once.
* Provides an entry point for actual cronjobs, such that it may be called directly from any cronjob, eg:
`php /path/to/ci/application/third_party/cronjob.php --ci-job-id=abc --once 'controller/method/argument'`

# Using as library w/o CodeIgniter

It is intended for use within an CI application, but can easily be used otherwise. The main-file containing the manager assumes that it will be either called from within CI, or on the commandline by a cronjob; so to incorporate it in any other library you can define `CRONTAB_AS_LIB` previous to including the manager, ie


    define('CRONTAB_AS_LIB',TRUE);
    require_once 'my/path/to/crontab.php';


# Files

1. `/application/third_party/crontab.php` Manager and CLI bootstrap
2. `/application/libraries/Crontab.php` CI-wrap
3. `/bin/crontab.php` CLI for manager (proof of concept)

# License LGPLv3