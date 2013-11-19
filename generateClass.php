<?php
class GenerateConfig {
    const PARAM_PREFIX = '_';
    const TAB_CHARACTER = '    ';
}

class GeneratePHPClass {
    private $_config;
    private $_class;
    private $_namespace;
    private $_className;
    private $_path;

    public function __construct($config) {
        if (empty($config) || !is_array($config))
            throw new Exception("Invalid class config received: ".print_r($config, 1));

        if (!isset($config['fqn']) || empty($config['fqn'])) // || !isset($config['props']) || empty($config['props']))
            throw new Exception("Required class fully qualified name (fqn) and/or class properties (props) not present in configuration");

        $this->_config = $config;
        // merge the constructor and properties
        if (!isset($this->_config['props']))
            $this->_config['props'] = array();

        if (isset($this->_config['construct']))
            array_unshift($this->_config['props'], $this->_config['construct']);
        $this->_extractClassDetails();
    }

    public function generate($overwrite = false) {
        $this->_beginClass();
        $this->_addProperties();
        $this->_addConstructor();
        $this->_addMethods();
        $this->_closeClass();

        echo $this->_class;
        $this->_saveClass($overwrite);
    }

    private function _beginClass() {
        $this->_class .= '<?php'.PHP_EOL;
        $this->_class .= 'namespace '.$this->_namespace.';'.PHP_EOL.PHP_EOL;
        $this->_class .= 'class '.$this->_className.' {'.PHP_EOL;
    }

    private function _addProperties() {
        foreach ($this->_config['props'] as $idx=>$prop) {
            if (is_array($prop))
                $prop = $prop['prop'];
            $this->_class .= GenerateConfig::TAB_CHARACTER.'private $'.GenerateConfig::PARAM_PREFIX.$prop.';'.PHP_EOL;
        }
        $this->_class .= PHP_EOL;
    }

    private function _addConstructor() {
        if (!isset($this->_config['construct']))
            return;

        $prop = $this->_config['construct'];

        // Props might have an fqn
        $propFqn = '';
        if (is_array($prop)) {
            if (isset($prop['fqn']) && !empty($prop['fqn']))
                $propFqn = '\\'.$prop['fqn'].' ';
            $prop = $prop['prop'];
        }

        $this->_class .= 
            GenerateConfig::TAB_CHARACTER.
                'public function __construct('.$propFqn.'$'.$prop.' = null) {'.PHP_EOL.
            GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                    '$this->'.GenerateConfig::PARAM_PREFIX.$prop.' = $'.$prop.';'.PHP_EOL.
            GenerateConfig::TAB_CHARACTER.
                '}'.PHP_EOL.PHP_EOL;

    }

    private function _addMethods() {
        foreach ($this->_config['props'] as $idx=>$prop) {
            // Props might have an fqn and/or a type
            $propFqn = ''; $propBool = false;
            if (is_array($prop)) {
                if (isset($prop['fqn']) && !empty($prop['fqn']))
                    $propFqn = '\\'.$prop['fqn'].' ';
                if (isset($prop['type']) && !empty($prop['type']) && strcmp($prop['type'], 'boolean') === 0)
                    $propBool = true;
                $prop = $prop['prop'];
            }

            // Getter method
            if (!$propBool)
                $this->_class .= 
                    GenerateConfig::TAB_CHARACTER.
                        'public function get'.ucfirst($prop).'() {'.PHP_EOL.
                    GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                            'return $this->'.GenerateConfig::PARAM_PREFIX.$prop.';'.PHP_EOL.
                    GenerateConfig::TAB_CHARACTER.
                        '}'.PHP_EOL.PHP_EOL;
            else
                $this->_class .= 
                    GenerateConfig::TAB_CHARACTER.
                        'public function is'.ucfirst($prop).'() {'.PHP_EOL.
                    GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                            'return empty($this->'.GenerateConfig::PARAM_PREFIX.$prop.') ? false : true;'.PHP_EOL.
                    GenerateConfig::TAB_CHARACTER.
                        '}'.PHP_EOL.PHP_EOL;

            // Setter method
            $this->_class .= 
                GenerateConfig::TAB_CHARACTER.
                    'public function set'.ucfirst($prop).'('.$propFqn.'$'.$prop.') {'.PHP_EOL.
                GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                        '$this->'.GenerateConfig::PARAM_PREFIX.$prop.' = $'.$prop.';'.PHP_EOL.
                GenerateConfig::TAB_CHARACTER.
                    '}'.PHP_EOL.PHP_EOL;
        }
    }

    private function _closeClass() {
        $this->_class .= '}'.PHP_EOL.PHP_EOL;
    }

    private function _saveClass($overwrite) {
        $filePath = $this->_path.$this->_className.'.php';
        // Do not overwrite if already exists
        if (file_exists($filePath) && !$overwrite)
            echo "Class already exists - not over-writing".PHP_EOL;

        // Ignore directory create errors
        @mkdir($this->_path, 0755, true);

        file_put_contents($filePath, $this->_class);
    }

    private function _extractClassDetails() {
        $namespaceParts = explode('\\', $this->_config['fqn']);
        $numParts = count($namespaceParts);
        $this->_className = $namespaceParts[$numParts - 1];
        foreach ($namespaceParts as $idx=>$parts) {
            if ($idx != ($numParts - 1)) {
                $this->_namespace .= $parts.'\\';
                $this->_path .= $parts.DIRECTORY_SEPARATOR;
            }
        }
        $this->_namespace = rtrim($this->_namespace, '\\');
    }
}

function printUsage() {
    echo "Usage: php generateClass.php -c <yaml model definition file> [ -f ] [ -h ]".PHP_EOL;
    echo "Options: Options can be specified in any order.".PHP_EOL;
    echo "      -h                               : Print this help message".PHP_EOL;
    echo "      -c <yaml class definition file > : configuration file containing model(s) description(s)".PHP_EOL;
    echo "      -f                               : Force overwriting of existing file [Default: false]".PHP_EOL;
    exit;
}

//xdebug_start_trace();

$options = getopt("hfc:");
if (isset($options['h']))
    printUsage();
    
if (!isset($options['c']) || empty($options['c']) || !file_exists($options['c'])) {
    echo "Error: model definition file not passed or does not exist".PHP_EOL;
    printUsage();
}

$config = $options['c'];
$overwrite = isset($options['f']) ? true : false;

foreach (yaml_parse_file($config) as $idx=>$class) {
    $gen = new GeneratePHPClass($class);
    $gen->generate($overwrite);
}
