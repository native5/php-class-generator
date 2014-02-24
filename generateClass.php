<?php
/**
 *  Copyright 2013 Native5. All Rights Reserved
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  You may not use this file except in compliance with the License.
 *
 *  You may obtain a copy of the License at
 *  http://www.apache.org/licenses/LICENSE-2.0
 *  or in the "license" file accompanying this file.
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *  PHP version 5.3+
 *
 * @category  Code Generation
 * @author    Shamik Datta <shamik@native5.com>
 * @copyright 2013 Native5. All Rights Reserved
 * @license   See attached LICENSE for details
 * @version   GIT: $gitid$
 * @link      http://www.docs.native5.com
 */
require_once 'vendor/autoload.php';

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

    // Flags to implement different patterns
    private $_immutable;
    private $_builderClass;
    private $_builder;
    private $_builtClass;

    public function __construct($config) {
        if (empty($config) || !is_array($config))
            throw new Exception("Invalid class config received: ".print_r($config, 1));

        if (!isset($config['fqn']) || empty($config['fqn'])) // || !isset($config['props']) || empty($config['props']))
            throw new Exception("Required class fully qualified name (fqn) and/or class properties (props) not present in configuration");

        $this->_config = $config;
        $this->_extractClassDetails();

        // merge the constructor and properties
        if (!isset($this->_config['props']))
            $this->_config['props'] = array();

        if (isset($this->_config['construct']))
            array_unshift($this->_config['props'], $this->_config['construct']);
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
        if (!isset($this->_config['construct']) && !$this->_immutable)
            return;

        $prop = $propFqn = '';
        if (isset($this->_config['construct'])) {
            $prop = $this->_config['construct'];

            // Props might have an fqn
            if (is_array($prop)) {
                if (isset($prop['fqn']) && !empty($prop['fqn']))
                    $propFqn = '\\'.$prop['fqn'].' ';
                $prop = $prop['prop'];
            }
        }

        if ($this->_immutable) {
            $this->_class .= 
                GenerateConfig::TAB_CHARACTER.
                    'public function __construct(\\'.$this->_builderClass.' $builder) {'.PHP_EOL;
            foreach ($this->_config['props'] as $idx=>$_prop) {
                if (is_array($_prop))
                    $_prop = $_prop['prop'];
                $this->_class .= 
                    GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                            '$this->'.GenerateConfig::PARAM_PREFIX.$_prop.' = $builder->get'.ucfirst($_prop).'();'.PHP_EOL;
            }
            $this->_class .= 
                GenerateConfig::TAB_CHARACTER.
                    '}'.PHP_EOL.PHP_EOL;

            // For an immutable (built) object also generate the createBuilder method
            $createBuilderParam = empty($prop) ? '' : (empty($propFqn) ? '' : $propFqn.' ').'$'.$prop.' = null';
            $createBuilderArg = empty($prop) ? '' : '$'.$prop;
            $this->_class .= 
                GenerateConfig::TAB_CHARACTER.
                    'public function createBuilder('.$createBuilderParam.') {'.PHP_EOL.
                GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                        'return new \\'.$this->_builderClass.'('.$createBuilderArg.');'.PHP_EOL.
                GenerateConfig::TAB_CHARACTER.
                    '}'.PHP_EOL.PHP_EOL;
        } else {
            $this->_class .= 
                GenerateConfig::TAB_CHARACTER.
                    'public function __construct('.$propFqn.'$'.$prop.' = null) {'.PHP_EOL.
                GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                        '$this->'.GenerateConfig::PARAM_PREFIX.$prop.' = $'.$prop.';'.PHP_EOL.
                GenerateConfig::TAB_CHARACTER.
                    '}'.PHP_EOL.PHP_EOL;
        }
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

            // Setter method - not for immutable objects
            if (!$this->_immutable) {
                $this->_class .= 
                    GenerateConfig::TAB_CHARACTER.
                        'public function set'.ucfirst($prop).'('.$propFqn.'$'.$prop.') {'.PHP_EOL.
                    GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                            '$this->'.GenerateConfig::PARAM_PREFIX.$prop.' = $'.$prop.';'.PHP_EOL;
                if ($this->_builder)
                    $this->_class .=
                        GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                                'return $this;'.PHP_EOL;
                $this->_class .=
                    GenerateConfig::TAB_CHARACTER.
                        '}'.PHP_EOL.PHP_EOL;
            }
        }

        // Add the build method for a builder
        if ($this->_builder)
                $this->_class .= 
                    GenerateConfig::TAB_CHARACTER.
                        'public function build() {'.PHP_EOL.
                    GenerateConfig::TAB_CHARACTER.GenerateConfig::TAB_CHARACTER.
                            'return new \\'.$this->_builtClass.'($this);'.PHP_EOL.
                    GenerateConfig::TAB_CHARACTER.
                        '}'.PHP_EOL.PHP_EOL;
            
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

        $this->_readClassPatterns();
    }

    private function _readClassPatterns() {
        if (empty($this->_config['class-patterns']))
            return;

        foreach ($this->_config['class-patterns'] as $idx=>$name) {
            switch ($name) {
                case "immutable":
                    // Check that the builder is defined under class-metadata
                    if (!isset($this->_config['class-metadata']['builderClass']))
                        throw new \Exception("Need class-metadata > builder defined for an immutable built pattern");
                    $this->_immutable = true;
                    $this->_builderClass = $this->_config['class-metadata']['builderClass'];
                    break;
                case "builder":
                    // Check that the builtClass is defined under class-metadata
                    if (!isset($this->_config['class-metadata']['builtClass']))
                        throw new \Exception("Need class-metadata > builtClass defined for the builder pattern");
                    $this->_builder = true;
                    $this->_builtClass = $this->_config['class-metadata']['builtClass'];
                    break;
                default:
                    break;
            }
        }
    }
}

// Read command line options
$options = getopt("hfc:");
if (isset($options['h']))
    printUsage();

// Check that a config file has been provided
if (!isset($options['c']) || empty($options['c']) || !file_exists($options['c'])) {
    echo "Error: model definition file not passed or does not exist".PHP_EOL;
    printUsage();
}

$config = $options['c'];
$overwrite = isset($options['f']) ? true : false;
generate($config, $overwrite);

// ****** Local Functions Follow ****** //

function generate($configFile, $overwrite) {
    $yaml = new \Symfony\Component\Yaml\Parser();
    try {
        $config = $yaml->parse(file_get_contents($configFile));
    } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
        printf("Unable to parse the YAML model definition file [ %s ]: %s", $configFile, $e->getMessage());
    }

    foreach ($config as $idx=>$class) {
        $gen = new GeneratePHPClass($class);
        $gen->generate($overwrite);
    }

    exit(0);
}

function printUsage() {
    echo "Usage: php generateClass.php -c <yaml model definition file> [ -f ] [ -h ]".PHP_EOL;
    echo "Options: Options can be specified in any order.".PHP_EOL;
    echo "      -h                               : Print this help message".PHP_EOL;
    echo "      -c <yaml class definition file > : configuration file containing model(s) description(s)".PHP_EOL;
    echo "      -f                               : Force overwriting of existing file [Default: false]".PHP_EOL;
    exit(1);
}

