<?php

//Wrapper functions for logger
$___oLogger = new logger();
___set_backtrace_level(0);

function ___set_backtrace_level($level) {
  global $___oLogger;
  $___oLogger->vSetBacktraceLevel($level);
}
function ___save_debug($var, $desc=''){
  ___add_event($var, $desc);
}
function ___add_event($var, $desc='') {
  global $___oLogger;
  $desc = strlen($desc) > 0 ? $desc . ': ' : '';
  if( is_array($var) || is_object($var) ) {
    $___oLogger->vAddEvent($desc . print_r($var, true));
  } elseif ($var === false) {
    $___oLogger->vAddEvent($desc . 'false');
  } elseif ($var === true) {
    $___oLogger->vAddEvent($desc . 'true');
  } else {
    $___oLogger->vAddEvent($desc . $var);
  }
}

  /**
   * Class for story different events to disk in various formats
   *
   * Requires PHP 5
   *
   * @version 3.01.080805
   *
   * Changelog:
   * -----
   * 3.01.080805
   *   * bug with empty lines in log
   * -----
   * 3.00.080313
   *   * bFullInfo has been replaced by iTraceLevel(default: 0 - no backtrace info)
   *   * generally changed DEBUG information policy.
   *   * _sFileLineDelimiter changed to "\r\n" (Windows format)
   * -----
   * 2.00.080128
   *   + Added possibility to write events without time, if $this->bLogFormat = false.
   *   * Now $this->bAutoStore variable is true by default.
   * -----
   * 1.00.071112
   *     Initial release
   * -----
   */
  class logger {

    /**
     * Array that contains all unstored events
     * @var array
     * @access protected
     */
    protected $_aEvent;
    /**
     * Count of unstored events
     * @var int
     * @access protected
     */
    protected $_iCount;
    /**
     * Name (or path+name) of log file
     * @var String
     * @access protected
     */
    protected $_sLFile;
    /**
     * Date format such as in date() function
     * @var String
     * @access protected
     */
    protected $_sDateFormat;
    /**
     * String, thap will be placed at the end of each line (usually \r\n or \n)
     * @var String
     * @access protected
     */
    protected $_sFileLineDelimiter;

    /**
     * Store log to disk automatically, when event was added.
     */
    public $bAutoStore;

    /**
     * Write events with time before it(log format) or not (simple text).
     */
    public $bLogFormat;

    /**
     * Backtrace level
     */
    protected $_iBacktraceLevel;

    /**
     * Constructor
     *
     * @param String log path and filename
     */
    function __construct($psFile='') {
      $this->_iCount = 0;
      $this->_aEvent = array();
      $this->_sDateFormat = 'Y-m-d H:i:s ';
      $this->_sFileLineDelimiter = "\r\n";
      if ($psFile == '') {
        $this->vSetFile(date('./' . 'y-m', time()) . '-events.log');
      } else {
        $this->vSetFile($psFile);
      }
      $this->bAutoStore = true;
      $this->bLogFormat = true;
      $this->_iBacktraceLevel = 0;
    }

    /**
     * Destructor. Save all events to disk
     *
     * @param void
     */
    function __destruct() {
      $this->bStore();
    }

    /**
     * Add new event to queue
     *
     * @param String $psEvent - text for logging
     * @param int $piTraceLevel - Max depth of ftace information
     * @return void
     * @access public
     */
    public function vAddEvent($psEvent='', $piTraceLevel=-1) {
      if(trim($psEvent) !== '') {
        $aTime = $this->_aGetTime();
        $this->_aEvent[$this->_iCount]['etime'] = $aTime['sec'];
        $this->_aEvent[$this->_iCount]['mtime'] = $aTime['usec'];
        if(!$this->bLogFormat) {
          $this->_aEvent[$this->_iCount]['event'] = trim($psEvent);
        } else {
          if(is_int($piTraceLevel)) {
            $sFunction = $this->sGetBacktraceInfo($piTraceLevel);
          }
          $this->_aEvent[$this->_iCount]['event'] = trim($psEvent) . $sFunction;
        }
        $this->_iCount++;
        if($this->bAutoStore) {
          $this->bStore();
        }
      }
    }

    /**
     * Set filename for output
     *
     * @param String $psFile log path and filename
     * @return void
     * @access public
     */
    public function vSetFile($psFile) {
      $sFilename = trim($psFile);
      if($sFilename !== '') {
        $this->_sLFile = $sFilename;
      } else {
        $this->_sLFile = null;
      }
    }

    public function vSetBacktraceLevel($piLevel) {
      if(is_int($piLevel) && $piLevel >= 0) {
        $this->_iBacktraceLevel = $piLevel;
      }
    }
    /**
     * Return backrtace info in string format.
     *
     * @param int $piActualLevel
     * @return String
     */
    protected function sGetBacktraceInfo($piActualLevel) {
      if(!is_int($piActualLevel) || $piActualLevel < 0) {
        $iActualLevel = $this->_iBacktraceLevel;
      } else {
        $iActualLevel = $piActualLevel;
      }
      if($iActualLevel < 1) {
        return '';
      }
      $_tmp = debug_backtrace();
      if(count($_tmp) < 2) {
        return '';
      }
      $sFunction = $this->_sFileLineDelimiter . $this->_sFileLineDelimiter;
      $iCurrentLevel = min(array(count($_tmp), $iActualLevel));
      for($i = 0; $i < $iCurrentLevel; $i++) {
        if(isset($_tmp[$i+1])) {
          $sFile = $_tmp[$i+1]['file'];
          $sLine = $_tmp[$i+1]['line'];
          $sFunction .= '  ' . $sFile . '(' . $sLine . ') ';
          if(isset($_tmp[$i+2])) {
            $sTClass = isset($_tmp[$i+2]['class']) ? trim($_tmp[$i+2]['class']) : "";
            if($sTClass !== '') {
              $sFunction  .= '$' . $sTClass;
              $sFunction  .= '->';
            }
            $sTMethod = trim($_tmp[$i+2]['function']);
            if($sTMethod !== '') {
              $sFunction .= $sTMethod . '() ';
            }
            $sFunction .= $this->_sFileLineDelimiter;
          }
        } else {
          break;
        }
      }
      $sFunction .= $this->_sFileLineDelimiter;
      return $sFunction;
    }

    /**
     * Store current event queue to disk
     *
     * @param void
     * @return bool
     * @access public
     */
    public function bStore() {
      if ($this->iEventsCount() < 1) {
        return true;
      }
      if ($this->_sLFile == null) {
        throw new Exception(__CLASS__ . ' : ' . 'Filename not specified.');
        return false;
      }
      if (!$handle = fopen($this->_sLFile, 'a')) {
        throw new Exception(__CLASS__ . ' : ' . "Can't open file for writing.");
        return false;
      }
      for ($i=0; $i < $this->_iCount; $i++) {
        if($this->bLogFormat) {
          $sDate = date($this->_sDateFormat, $this->_aEvent[$i]['etime']);
        } else {
          $sDate = '';
        }
        $sStr = $this->_aEvent[$i]['event'] . $this->_sFileLineDelimiter;
        fwrite($handle, $sDate . $sStr);
      }
      fclose($handle);
      $this->_aEvent = array();
      $this->_iCount = 0;
      return true;
    }

    /**
     * return count of unsaved enents
     *
     * @param void
     * @return int
     * @access public
     */
    public function iEventsCount() {
      return $this->_iCount;
    }

    protected function _aGetTime() {
      list($usec, $sec) = explode(" ", microtime());
      $aTume['sec']  = $sec;
      $aTume['usec'] = $usec;
      return $aTume;
    }
  }
?>
