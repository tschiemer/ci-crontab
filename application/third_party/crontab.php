<?php

/**
 * Crontab Manager (intended for CodeIgniter)
 * 
 * Interface for CLI crontab. Differentiates between cronjobs managed
 * by itself and external cronjobs such as not to interfere.
 * 
 * Many thanks to Jonathon Hill for the inspiration (https://github.com/compwright/codeigniter-cli-bootstrap)
 * 
 * @todo Send output to somebody?
 * @todo Log anything?
 * @todo Add CI-Index as parameter?
 * 
 * @version 2013/10/29
 * @license LGPL v3 http://www.gnu.org/licenses/lgpl-3.0.txt 
 * @copyright 2013 Philip Tschiemer, <tschiemer@filou.se>
 * @link https://github.com/tschiemer/ci-crontab
 */

class Crontab_Manager {
    
    /**
     * Signature of loaded crontab
     * @var string
     */
    var $_file_signature = NULL;
    
    /**
     * Arrays of owned and foreign cronjobs
     * @var array
     */
    var $_jobs = array('my'=>NULL,'other'=>NULL);
    
    /**
     * Id of last added job.
     * @var string
     */
    var $_last_job_id = NULL;
    
    /**
     * Id settings
     * @var array
     */
    var $_ids = array(
        'regex'     => "0-9a-zA-Z_-",
        'length'    => 8,
        'alphabet'  => '0123456789abcdefghijklmnopqrstruvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_'
    );
    
    /**
     * Command to run this script
     * @var string
     */
    var $_script_cmd;
    
    /**
     * Option labels
     * @var array
     */
    var $_opts = array(
        'job-id'    => 'ci-job-id',
        'once'      =>'once'
    );
    
    /**
     * Effective commands to use
     * @var array
     */
    var $_cmd = array(
        'list'  =>'crontab -l',
        'clear' =>'crontab -r',
        'write' =>'crontab'
    );
    
    /**
     * Labels/keys to use for 'when' arrays
     * @var array
     */
    var $_when_labels = array('seconds','minutes','hours','day_of_week','day_of_month');
    
    
    /**
     * 
     */
    public function __construct($config=array())
    {
        if (isset($config['script-cmd']))
        {
            $this->_script_cmd = $config['script-cmd'];
        }
        else
        {
            $this->_script_cmd = 'php ' . escapeshellarg(__FILE__);
        }
    }
    
    /**
     * 
     * @param boolean $reload
     * @return array|boolean FALSE on fail
     */
    public function load_jobs()
    {   
        if ( ! $this->read_file($file,$this->_file_signature))
        {
            $this->_jobs['my'] = NULL;
            $this->_jobs['other'] = NULL;
            return FALSE;
        }
        else
        {
            $this->_jobs['my'] = array();
            $this->_jobs['other'] = array();
        }

        $cmd = str_replace('/', '\/', $this->_script_cmd);
        $regex = "/^((?:[^ ]+ ){5}){$cmd} --{$this->_opts['job-id']}=([{$this->_ids['regex']}]+)((?: --{$this->_opts['once']})?) (.+)$/";

        foreach($file as $job)
        {
            if (preg_match($regex, $job, $matches))
            {
                $when = explode(' ',trim($matches[1]));
                
                $this->_jobs['my'][$matches[2]] = array(
                    'cmd'   => $this->unescapeshellarg($matches[4]),
                    'when' => array_combine($this->_when_labels, $when),
                    'once'  => (bool)(0 < strlen($matches[3]))
                );
            }
            else
            {
                $this->_jobs['other'][] = $job;
            }
        }
        
        return TRUE;
    }
    
    
    /**
     * 
     * @param string $cmd
     * @param string|array $when
     * @param array $options
     * @return Crontab_Manager
     */
    public function add_job($cmd, $when, $options = array())
    {   
        // Sanitize or generate job id
        $regex = "/^([ˆ{$this->_ids['regex']}]+)$/";
        
        if (! empty($options['job-id']) and preg_match($regex,$options['job-id']))
        {
            $job_id = $options['job-id'];
        }
        else
        {
            $abc = $this->_ids['alphabet'];
            $abc_len = strlen($abc);
            
            $job_id = '';
            for($i=0; $i < $this->_ids['length']; $i++)
            {
                $job_id .=  substr($abc, rand(0,$abc_len-1), 1);
            }
        }
//        print_r($options);
//        print_r($job_id);
//        exit;
        
        // Bring when into internally used form
        if (is_string($when) and strpos($when,' '))
        {
            $when = explode(' ',trim($when));
            
        }
        if (is_array($when))
        {
            $when = array_combine($this->_when_labels,array_values($when));
        }
        
        // Run cronjob only once?
        $is_once = (isset($options['once']) and $options['once']);
        
        // Add/overwrite job
        $this->_jobs['my'][$job_id] = array(
            'cmd'       => $cmd,
            'when'      => $when,
            'once'      => $is_once
        );
        
        // Save job id
        $this->_last_job_id = $job_id;
        
        return $this;
    }
    
    /**
     * Retrieve signature of loaded cronjob file, NULL iff not loaded
     * @return string|NULL
     */
    public function get_signature()
    {
        return $this->_file_signature;
    }
    
    /**
     * Retrieve id of last added/updated cronjob, NULL if none added/updated
     * @return string|NULL
     */
    public function last_job_id()
    {
        return $this->_last_job_id;
    }
    
    public function get_jobs()
    {
        return $this->_jobs['my'];
    }
    
    /**
     * Removes chosen job from list, if exists.
     * @param string $job_id
     * @return Crontab_Manager
     */
    public function remove_job($job_id)
    {
        if (isset($this->_jobs['my'][$job_id]))
        {
            unset($this->_jobs['my'][$job_id]);
        }
        
        return $this;
    }
    
    /**
     * 
     * @param integer $seconds
     * @param integer $minutes
     * @param integer $hours
     * @param integer $days
     * @return string
     */
    public function when_from_now($seconds = 0, $minutes = 0, $hours = 0, $days = 0)
    {
        /**
         * @todo compute actual time from now
         */
        return array_combine($this->_when_labels,array(
            $seconds, $minutes, $hours, $days, '*'
        ));
    }
    
    public function commit()
    {
        if ($this->_file_signature === NULL)
        {
            return FALSE;
        }
        
        $my_jobs = array();
        foreach($this->_jobs['my'] as $job_id => $job)
        {
            // when php __FILE__ --ci-cronjob-id=ID [--once] cmd

            $when = implode(' ', $job['when']);

            $id = "--{$this->_opts['job-id']}={$job_id}";

            if ($job['once'])
            {
                $once = " --{$this->_opts['once']}"; // please note the spaces
            }
            else
            {
                $once = '';
            }

            $cmd = escapeshellarg($job['cmd']);

            $my_jobs[] =  "{$when} {$this->_script_cmd} {$id}{$once} {$cmd}";
        }
        
        $all_jobs = array_merge($my_jobs, $this->_jobs['other']);

        //echo implode("\n",$all_jobs);
        return $this->write_file($all_jobs,$this->_file_signature);
    }
    
    /**
     * @return array|null
     */
    public function read_file(&$file=NULL,&$signature = NULL)
    {   
        $result_code = $this->exec('list', FALSE, $out);
        
        if ($result_code)
        {
            $file = array();
            $signature = '';
            return TRUE;
        }
        
        $file = explode("\n",$out);
        $signature = md5($out);
        
        return TRUE;
    }
    
    /**
     * 
     * @param array|string $cronjobs
     * @return boolean
     */
    public function write_file($cronjobs,$expected_signature=NULL)
    {
        $this->read_file($file, $now_signature);
        
        if ($expected_signature !== NULL and $expected_signature != $now_signature)
        {
                return FALSE;
        }
     
        if (is_array($cronjobs))
        {
            $cronjobs = implode("\n",$cronjobs);
        }
        
        // did the cronjobs actually change?
        $write_signature = md5($cronjobs);
        if ($write_signature == $now_signature)
        {
            return 0; // no need to do anything
        }
        
        return $this->exec('write',$cronjobs,$out);
    }
    
    /**
     * 
     */
    public function clear_file()
    {
        return $this->exec('clear');
    }
    
    public function unescapeshellarg($arg)
    {
        if (preg_match("/^'(.+)'$/",$arg,$m))
        {
            return $m[1];
        }
        return $arg;
    }
    
    public function exec($cmd,$in = FALSE, &$out = FALSE)
    {
        if (!isset($this->_cmd[$cmd]))
        {
            return 1;
        }
        
        $result = NULL;
        
        // If nothing is written to the process, go the easy way.
//        if ($in === FALSE)
//        {
//            $result = shell_exec($this->_cmd[$cmd]);
//            $code = 0;
//        }
//        else
        {
            $fd = array(
                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
                2 => array("pipe", "w") // stderr is a file to write to
            );
            $pipes = array();
            
            $proc = proc_open($this->_cmd[$cmd], $fd, $pipes);
            
            if ($proc === FALSE)
            {
                $code = 1;
            }
            else
            {
                if ($in !== FALSE)
                {
                    fwrite($pipes[0],$in);
                }
                fclose($pipes[0]);
                
                $result = '';
                while(! feof($pipes[1]))
                {
                    $result .= fgets($pipes[1]);
                }

                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $code = proc_close($proc);
            }
        }
        if ($out !== FALSE)
        {
            $out = $result;
        }
        
        return $code;
    }
}


/**
 * If called from the command line, and not
 * from within CI and not with the flag CRONTAB_AS_LIB set,
 * assume this is a cronjob running and try to process it.
 */
if (strtolower(php_sapi_name()) == 'cli' and  ! defined('CRONTAB_AS_LIB') and ! defined('BASEPATH'))
{
    if (defined('CRONTAB_SCRIPT_CMD') and strlen(CRONTAB_SCRIPT_CMD))
    {
        $crontab = new Crontab_Manager(CRONTAB_SCRIPT_CMD);
    }
    else
    {
        $crontab = new Crontab_Manager();
    }
    
    $longopt = $crontab->_opts;
    foreach($longopt as $k => $v)
    {
        switch($k)
        {
            case 'job-id':
                $longopt[$k] = "{$v}:";
                break;
            
            case 'ci-index':
                $longopt[$k] = "{$v}::";
            
            case 'once':
            default:
                $longopt[$k] = "{$v}";
                break;
        }
    }
    
    // Get CLI options
    $options = getopt('',array_values($longopt));
    
    if ($argc - count($options) - 1 != 1)
    {
        die('wrong argument count');
    }
    
    // Make options available through standard keys
    foreach(array_keys($longopt) as $k)
    {
        if (isset($options[$crontab->_opts[$k]]))
        {
            $options[$k] = $options[$crontab->_opts[$k]];
        }
    }
    
    // If is once-job, remove from crontab
    if (isset($options['job-id']) and isset($options['once']))
    {
        $crontab->load_jobs();
        $crontab->remove_job($options['job-id'])->commit();
    }
    
    // If CI index is unknown, try to guess.
    if ( ! isset($options['ci-index']))
    {   
        $options['ci-index'] = realpath(dirname(__FILE__) . '/../../index.php');
    }
    if (! file_exists($options['ci-index']))
    {
        die('Path to CodeIgniter index unknown! Tried with '.$options['ci-index'] . "\n");
    }
    
    // Set URI
    $uri = $argv[$argc-1];
    if (strpos($uri, '/') != 0)
    {
        $uri = '/' . $uri;
    }
    
    $_SERVER['argv'][0] = $options['ci-index'];
    $_SERVER['argv'][1] = $uri;
    $_SERVER['argc']    = 2; 
    $_SERVER['PATH_INFO'] = $uri;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SERVER_NAME'] = 'localhost';
    
    // Clear arguments
    for($i = 2; $i < $argc; $i++)
    {
        unset($_SERVER['argv'][$i]);
        //$_SERVER['argv'][$i] = '';
    }
    
    chdir(dirname($options['ci-index']));
    
    ob_start();
    require $options['ci-index'];
    $output = ob_get_clean();
    
    echo $output;
    
    /**
     * @todo send mail to someone?
     */
    
    /**
     * @todo log?
     */
}


/** End of file crontab.php **/
/** Location: ./application/third_party/crontab.php **/