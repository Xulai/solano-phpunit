    <?php
/*
 * This file is part of Solano-PHPUnit.
 *
 * (c) Solano Labs https://www.solanolabs.com/
 *
 */

if (getenv('TDDIUM')):

/**
 * PHPUnit listener for Solano-PHPUnit
 *
 * @package    Solano-PHPUnit
 * @author     Isaac Chapman <isaac@solanolabs.com>
 * @copyright  Solano Labs https://www.solanolabs.com/
 * @link       https://www.solanolabs.com/
 */
class SolanoLabs_PHPUnit_Listener extends PHPUnit_Util_Printer implements PHPUnit_Framework_TestListener
{
    /**
     * @var    string
     */
    private $outputFile = 'tddium_output.json';

    /**
     * @var    array
     */
    private $excludeFiles = array();

    /**
     * @var    string
     */
    private $currentTestSuiteName = '';

    /**
     * @var    array
     */
    private $currentTestcase;

    /**
     * @var    array
     */
    private $files = array();

    /**
     * @var    string
     */
    private $stripPath = '';

    /**
     * Constructor.
     *
     * @param string   $outputFile
     */
    public function __construct($outputFile = '', $excludeFiles = '')
    {
        $this->outputFile = $outputFile;
        if ($excludeFiles) {
            $this->excludeFiles = explode(',', $excludeFiles);
        }
        $this->stripPath = getenv('TDDIUM_REPO_ROOT') ? getenv('TDDIUM_REPO_ROOT') : getcwd();
    }

    /**
     * A test started.
     *
     * @param PHPUnit_Framework_Test $test
     */
    public function startTest(PHPUnit_Framework_Test $test)
    {
        if (getenv('TDDIUM')) {
            global $tddium_output_buffer;
            $tddium_output_buffer = "";
        }
        if (!$test instanceof PHPUnit_Framework_Warning) {
            $testcase = array('id' => '', 'address' => '', 'status' => '', 'stderr' => '', 'stdout' => '', 'file' => '');
            if ($test instanceof PHPUnit_Framework_TestCase) {
                $class = new ReflectionClass($test);
                $className = $class->getName();
                $testName = $test->getName();
                $testcase['id'] = $className . '::' . $testName;
                $testcase['file'] = $class->getFileName();
                if ($class->hasMethod($testName)) {
                    $testcase['address'] = $className . '::' . $testName;
                } else {
                    // This is a paramaterized test...get the address from the testsuite
                    $testcase['address'] = $this->currentTestSuiteName;
                }
                $this->currentTestcase = $testcase;
            }
       
        }
    }

    /**
     * A test ended.
     *
     * @param PHPUnit_Framework_Test $test
     * @param float                  $time
     */
    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        $testcase = $this->currentTestcase;
        if (!$testcase['status']) {
            $testcase['status'] = 'pass';
            $testcase['time'] = $time;
        }
        if (method_exists($test, 'hasOutput') && $test->hasOutput()) {
            $testcase['stdout'] = $test->getActualOutput();
        }
        if (getenv('TDDIUM')) {
            global $tddium_output_buffer;
            $testcase['stdout'] .= $tddium_output_buffer;
        }
        $this->addTestCase($testcase);
        $this->currentTestcase = array();
    }

    /**
     * An error occurred.
     *
     * @param PHPUnit_Framework_Test $test
     * @param Exception              $e
     * @param float                  $time
     */
    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {   
        $this->addNonPassTest('error', $test, $e, $time);
    }

    /**
     * A failure occurred.
     *
     * @param PHPUnit_Framework_Test                 $test
     * @param PHPUnit_Framework_AssertionFailedError $e
     * @param float                                  $time
     */
    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time)
    {
        $this->addNonPassTest('fail', $test, $e, $time);
    }

    /**
     * Incomplete test.
     *
     * @param PHPUnit_Framework_Test $test
     * @param Exception              $e
     * @param float                  $time
     */
    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        $this->addNonPassTest('error', $test, $e, $time, 'Incomplete Test: ');
    }

    /**
     * Risky test.
     *
     * @param PHPUnit_Framework_Test $test
     * @param Exception              $e
     * @param float                  $time
     */
    public function addRiskyTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        $this->addNonPassTest('error', $test, $e, $time, 'Risky Test: ');
    }

    /**
     * Skipped test.
     *
     * @param PHPUnit_Framework_Test $test
     * @param Exception              $e
     * @param float                  $time
     */
    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        if (count($this->currentTestcase)) {
            $this->addNonPassTest('skip', $test, $e, $time, 'Skipped Test: ');
        } else {
            // PHPUnit skips tests with unsatisfied @depends without "starting" or "ending" them.
            $this->startTest($test);
            $this->addNonPassTest('skip', $test, $e, 0, 'Skipped Test: ');
            $this->endTest($test, 0);
        }
    }

    /**
     * Add a non-passing test to the output
     *
     * @param string                 $status
     * @param PHPUnit_Framework_Test $test
     * @param Exception              $e
     * @param string                 $stderrPrefix
     */
    private function addNonPassTest($status, PHPUnit_Framework_Test $test, Exception $e, $time, $stderrPrefix = '')
    {
        $this->currentTestcase['status'] = $status;
        $this->currentTestcase['time'] = $time;
        if (method_exists($test, 'hasOutput') && $test->hasOutput()) {
            $this->currentTestcase['stdout'] = $test->getActualOutput();
        }
        $this->currentTestcase['stderr'] = $stderrPrefix . $e->getMessage();
        $traceback = PHPUnit_Util_Filter::getFilteredStacktrace($e, false);
        // Strip path from traceback?
        for($i = 0; $i < count($traceback); $i++) {
            if (0 === strpos($traceback[$i]['file'], $this->stripPath)) {
                $traceback[$i]['file'] = substr($traceback[$i]['file'], strlen($this->stripPath) + 1);
            }
        }
        $this->currentTestcase['traceback'] = $traceback;
    }

    /**
     * A testsuite started.
     *
     * @param PHPUnit_Framework_TestSuite $suite
     */
    public function startTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        $this->currentTestSuiteName = $suite->getName();
    }

    /**
     * A testsuite ended.
     *
     * @param PHPUnit_Framework_TestSuite $suite
     */
    public function endTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        $this->currentTestSuiteName = '';
        $this->currentTestSuiteAddress = '';
    }

    /**
     * Add testcase to the output
     *
     * @param PHPUnit_Framework_TestSuite $suite
     */
    private function addTestCase($testcase) 
    {
        $file = $testcase['file'];
        if (0 === strpos($file, $this->stripPath)) {
            $file = substr($file, strlen($this->stripPath) + 1);
        }
        unset($testcase['file']);
        if (!isset($this->files[$file])) {
            $this->files[$file] = array();
        }
        $this->files[$file][] = $testcase;
    }

    /**
     * Write output file.
     * Called by PHPUnit_Framework_TestResult::flushListeners()
     */
    public function flush()
    {
        $this->convertOutputToUTF8();
        if (file_exists($this->outputFile)) {
            // If the output file exists, add to it
            $json = json_decode(file_get_contents($this->outputFile), true);
            if (is_null($json) || !isset($json['byfile']) || !is_array($json['byfile'])) {
                // JSON could not be read
                echo("### ERROR: JSON data could not be read from " . $this->outputFile . "\n");
                $jsonData = array('byfile' => $this->files);
            } else {
                $jsonData = array('byfile' => array_merge($json['byfile'], $this->files));
            }
        } else {
            // Output file doesn't exist, create fresh.
            $jsonData = array('byfile' => $this->files);
        }

        if (count($this->excludeFiles)) {
            $jsonData = array('byfile' => array_merge($jsonData['byfile'], $this->generateExcludeFileNotices()));
        }

        $file = fopen($this->outputFile, 'w');
        if (!defined('JSON_PRETTY_PRINT')) { define('JSON_PRETTY_PRINT', 128); } // JSON_PRETTY_PRINT available since PHP 5.4.0
        fwrite($file, json_encode($jsonData, JSON_PRETTY_PRINT));
        fclose($file);
    }

    /**
     * Create output for files that were excluded in the XML config
     */
    private function generateExcludeFileNotices()
    {
        $skipFiles = array();
        foreach($this->excludeFiles as $file) {
            $shortFilename = $file;
            if (0 === strpos($file, $this->stripPath)) {
                $shortFilename = substr($file, strlen($this->stripPath) + 1);
            }
            // Can we inspect the file?
            try {
                $fileMethods = array();
                $declaredClasses = get_declared_classes();
                PHPUnit_Util_Fileloader::checkAndLoad($file);
                $newClasses = array_diff(get_declared_classes(), $declaredClasses);
                foreach($newClasses as $className) {
                    $class = new ReflectionClass($className);
                    if ($class->implementsInterface('PHPUnit_Framework_Test')) {
                        $methods = $class->getMethods();
                        foreach ($methods as $method) {
                            if (0 === strpos($method->name, 'test')) {
                                $fileMethod = array('id' => $className . '::' . $method->name,
                                    'address' => $className . '::' . $method->name,
                                    'status' => 'skip',
                                    'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Excluded by <exclude/> in configuration',
                                    'stdout' => '',
                                    'time' => 0,
                                    'traceback' => array());
                                $fileMethods[] = $fileMethod;
                            }
                        }
                    }
                }
                if (count($fileMethods)) {
                    $skipFiles[$shortFilename] = $fileMethods;
                } else {
                    $skipFiles[$shortFilename] = array(array('id' => $shortFilename,
                        'address' => $shortFilename,
                        'status' => 'skip',
                        'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Excluded by <exclude/> and no test methods found',
                        'stdout' => '',
                        'time' => 0,
                        'traceback' => array()));
                }
            } catch (Exception $e) {
                $skipFiles[$shortFilename] = array(array('id' => $shortFilename,
                    'address' => $shortFilename,
                    'status' => 'skip',
                    'stderr' => 'Skipped Test File: ' . $shortFilename . "\n" . 'Excluded by <exclude/> in configuration and could not inspect file:' . "\n" . $e->getMessage(),
                    'stdout' => '',
                    'time' => 0,
                    'traceback' => array()));
            }

        }
        return $skipFiles;
    }

    /**
     * Convert to utf8
     */
    private function convertOutputToUTF8()
    {
        array_walk_recursive($this->files, function (&$input) {
        if (is_string($input)) {
                $input = PHPUnit_Util_String::convertToUtf8($input);
            }
        });
        unset($input);
    }
}

endif;