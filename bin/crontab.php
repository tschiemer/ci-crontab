<?php

/**
 * Proof-of-concept CLI interface for Crontab Manager
 * 
 * @version 2013/10/29
 * @license LGPL v3 http://www.gnu.org/licenses/lgpl-3.0.txt 
 * @copyright 2013 Philip Tschiemer, <tschiemer@filou.se>
 * @link https://github.com/tschiemer/ci-crontab
 */

if ($argc <= 1)
{
    echo "Usage:\n";
    echo "\t php {$argv[0]} --list --ci-index '/path/to/ci/index.php' \n";
    echo "\t php {$argv[0]} --add --ci-index '/path/to/ci/index.php' [--once] [--job-id=ID] '* * * * *' '/controller/method/arguments'\n";
    echo "\t php {$argv[0]} --remove --ci-index '/path/to/ci/index.php' --job-id=ID\n";
    
    echo "\nAnd to actually run a job as intended you call:\n";
    echo "\t php {$argv[0]} --ci-job-id=ID [--once] '/controller/method/argument'\n";
    exit;
}

// possible actions & options
$actions = array('add','remove','list');
$possible_options = array('job-id:','once','ci-index:');

// Get CLI options
$options = getopt('',array_merge($actions,$possible_options));

// Get CI-Index path
$ci_base = dirname($options['ci-index']);
$libsrc = "{$ci_base}/application/third_party/crontab.php";

// .. and verify existence of file.
if ( !file_exists($libsrc))
{
    die("Invalid CodeIgniter index path: {$options['ci-index']}\n");
}

// If not called with any action-parameters (add,remove,list) assume cronjob call..
if (count(array_diff($actions, array_keys($options))) == count($actions))
{
    require_once $libsrc;
    exit;
}
// .. otherwise run run run
else
{
    define('CRONTAB_AS_LIB',TRUE);
    require_once $libsrc;
}


/**
 * Load crontab manager
 */

$crontab = new Crontab_Manager();


// Identify action to run
foreach($actions as $a)
{
    if (array_key_exists($a,$options))
    {
        $action = $a;
        break;
    }
}

// .. and run action! ..
switch ($action)
{
    case 'list':
        $crontab->load_jobs();
        print_r($crontab->get_jobs());
        break;
    
    case 'add':
        $cron_options = array();
        
        if (isset($options['once']))
        {
            $cron_options['once'] = TRUE;
        }
        if (isset($options['job-id']))
        {
            print_r($options);
            $cron_options['job-id'] = $options['job-id'];
        }
        
        if ($argc - count($options) - 1 != 2)
        {
            die("Wrong argument count!\n");
        }
        
        $when = $argv[$argc-2];
        $job = $argv[$argc-1];
        
        $crontab->load_jobs();
        
        $crontab->add_job($job, $when,$cron_options);
        $job_id = $crontab->last_job_id();
        
        $crontab->commit();
        
        echo "Added job with id={$job_id}\n";
        
        break;
    
    case 'remove':
        if (!isset($options['job-id']))
        {
            die("Missing job id of job to remove.\n");
        }
        
        $job_id = $options['job-id'];
        
        $crontab->load_jobs();
        $list = $crontab->get_jobs();
        if (!isset($list[$job_id]))
        {
            die("No such job with ID={$job_id}\n");
        }
        
        $crontab->remove_job($job_id);
        $crontab->commit();
        
        echo "Removed job.\n";
        
        break;
}

/** End of file crontab.php **/
/** Location: ./bin/crontab.php **/