<?php

// TODO: refoactoring

// 1 init
// 2 require init and load
// 3 test all test and load / init all load / init in parent folder in order lower to upper

echo 'File: ' . __FILE__ . PHP_EOL;
echo 'Parse parameters...' . PHP_EOL;

const DEFAULT_CONSTS = [
    'EXTRACT_BLOCK_NB_LINE_AROUND' => 3,
    'SOURCE_TESTS' => __DIR__ . '/tests',
    'CONFIG_FILE' => [__DIR__ . '/config.php'],
    'LOADER_FILE' => [__DIR__ . '/load.php'],
    'BLACKLISTED_TESTS' => [],
    'WHITELISTED_TESTS' => [],
];

function is_parameter($str_param)
{
    if (empty($str_param)) return false;
    if (substr($str_param, 0, 2) === '--') return substr($str_param, 2);
    if (substr($str_param, 0, 1) === '-') return substr($str_param, 1);
    return false;
}

function parse_parameter($argv)
{
    $parameter = [];

    // parsing des parametres
    for ($i = 1; $i < count($argv); $i++) {
        // execution du test qui retourne string ou false (string est la nom du param entré)
        $param_name = is_parameter($argv[$i]);

        // si le param est false, pas de param
        if ($param_name === false) continue;

        // si le param est true, trouver la fin des valeurs
        for ($j = $i + 1; $j < count($argv) && is_parameter($argv[$j]) === false; $j++) {}

        // copier une partie du tableau des paramètres
        $parameter[$param_name] = array_slice($argv, $i + 1, $j - $i - 1, true);

        // reprendre $i a la suite pour skip les valeur trouvées
        $i = $j - 1;
    }

    // si une valeur est vide (paramètre seul), le transformer en true
    foreach ($parameter as $key => $value) if (empty($value)) $parameter[$key] = true;

    return $parameter;
}

function echo_help($described_script_param)
{
    $scrip_name = pathinfo(__FILE__, PATHINFO_BASENAME);
    $scrip_name_space = str_repeat(' ', strlen($scrip_name));
    echo 'Usage:' . PHP_EOL;
    echo 'php ' . $scrip_name . ' [OPTIONS...]' . PHP_EOL;
    foreach ($described_script_param as $value) {
        echo '    -' . $value['short'] . '|--' . $value['long'] . ' ' . $value['value'] . ($value['optional'] ? '' : ' Obligatoire') . PHP_EOL;
    }

    echo PHP_EOL;
    echo 'exemples:' . PHP_EOL;
    echo '$ php ' . $scrip_name .       '                                                      # use default : ./config.php and ./load.php' . PHP_EOL;
    echo '$ php ' . $scrip_name .       ' -c config_abc.php                                    # use custom config file  ./config_abc.php' . PHP_EOL;
    echo '$ php ' . $scrip_name .       ' -c config_abc.php config_def.php                     # use custom config files ./config_abc.php and overwrite it with ./config_def.php' . PHP_EOL;
    echo '$ php ' . $scrip_name .       ' -l load_abc.php                                      # use custom loader file  ./load_abc.php' . PHP_EOL;
    echo '$ php ' . $scrip_name .       ' -l load_abc.php load_def.php                         # use custom loader files ./load_abc.php ./load_def.php' . PHP_EOL;
    echo '$ php ' . $scrip_name .       ' -c /somewhere/config.php -l /somewhere/loader.php    # use custom config and loader files /somewhere/config.php and loader /somewhere/loader.php' . PHP_EOL;

    echo PHP_EOL;
    echo 'what is config ?' . PHP_EOL;
    echo '    config.php is the file for constants to configure the script' . PHP_EOL;
    echo '    write a php code with a list of `decalre(\'config_name\', \'value\');` or `const config_name = \'value\';`' . PHP_EOL;
    echo 'what is load ?' . PHP_EOL;
    echo '    load.php is the file for load before all test and for the whole execution of the script' . PHP_EOL;
    echo '    but, in tests folder, you can add load.php to load/init the folder of test' . PHP_EOL;

    echo PHP_EOL;
    echo 'Conseil d\'utilisation:' . PHP_EOL;
    echo '    crée une arborecence de dossier celon vos besoin commencant par 3 chiffre' . PHP_EOL;
    echo '    placer des loader dans les dossier dont vous avez besoin, ' . $scrip_name . ' vas loader recurcivement' . PHP_EOL;
    echo '    pour deactiver un test ou un dossier de test, prefixez le avec disabled_' . PHP_EOL;
    echo 'Q: et comment j\'initialise a chaque dossier de test et chaque test ?' . PHP_EOL;
    echo '    pour faire une initialisation a chaque test, placez un fichier init.php dans le dossier le quel il dois initialiser' . PHP_EOL;
    echo '    ces fichier sont inicialisé recursivement du plus bas au plus haut dans les dossier' . PHP_EOL;

    echo PHP_EOL;
}

function scan_plus($path)
{
    return array_filter(scandir($path),
    static function ($x) {
        return !in_array($x, ['.', '..']);
    });
}

function extract_line(string $file, int $line)
{
    $content = file_get_contents($file);
    $content_explode = explode(PHP_EOL, $content);

    return $content_explode[$line - 1] ?? 'non_trouve';
}

function extract_block(string $file, int $line)
{
    $content = file_get_contents($file);
    $content_explode = explode(PHP_EOL, $content);

    $middle = $line - 1;
    $start = $middle - EXTRACT_BLOCK_NB_LINE_AROUND;
    $end = $middle + EXTRACT_BLOCK_NB_LINE_AROUND;

    $string = '';

    for ($i = $start; $i <= $end; $i++) {
        $string .= $i === $middle ? '>' : '|';
        $string .= ($content_explode[$i] ?? '') . PHP_EOL;
    }

    return $string;
}

set_error_handler('exceptions_error_handler');

function exceptions_error_handler($severity, $message, $filename, $lineno) {
    $er = error_reporting();
    if ($er == 0) {
        return;
    }
    if ($er & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}

class Testeur {

    private $tests = [];

    private function result($reusite, $options = [])
    {
        $trace = debug_backtrace(0);
        array_shift($trace);
        array_pop($trace);

        $this->tests[] = [
            'reusite' => $reusite,
            'trace' => $trace,
            'options' => $options,
        ];
    }

    function resultat_positif()
    { return array_sum(array_column($this->tests, 'reusite')) === count($this->tests); }

    function nombre_test()
    { return count($this->tests); }

    function display_resultats()
    {
        foreach ($this->tests as $value) {
            if (isset($value['reusite']) && $value['reusite'] === true) continue;

            foreach ($value['trace'] as $v) {
                $v['args'] = array_map(static function($x) {
                    if (is_string($x)) return $x;
                    if (is_bool($x)) return $x ? 'true' : 'false';
                    if (is_array($x)) return json_encode($x);
                    if (is_object($x)) return $x->__toString();

                    return $x . '';
                }, $v['args']);

                echo 'Test invalid: ' . $v['file'] . ':' . $v['line'] . ' ' . $v['class'] . $v['type'] . $v['function'] . '(' . implode(', ', $v['args']) . ')' . PHP_EOL .
                '<pre>' . PHP_EOL . extract_block($v['file'], $v['line']) . '</pre>' . PHP_EOL;
            }
        }
    }

    function estVrais($vrais)
    { $this->result($vrais === true); }

    function estFaux($faux)
    { $this->result($faux === false); }

    function estEgal($attendu, $recu, $strict = true)
    { $this->result($strict ? $attendu === $recu : $attendu == $recu, ['strict' => $strict]); }

    function estPasEgal($attendu, $recu, $strict = true)
    { $this->result($strict ? $attendu !== $recu : $attendu != $recu, ['strict' => $strict]); }

    function estCassant($callable = null)
    {
        try {
            $callable();
            $this->result(false);
        } catch (Throwable $e) {
            $this->result(true);
        }
    }

    function estNonCassant($callable = null)
    {
        try {
            $callable();
            $this->result(true);
        } catch (Throwable $e) {
            $this->result(false);
        }
    }

}

$parameter = parse_parameter($argv);
$described_script_param = [
    [
        'short' => 'c',
        'long' => 'config',
        'value' => 'array',
        'optional' => true,
    ],
    [
        'short' => 'l',
        'long' => 'load',
        'value' => 'array',
        'optional' => true,
    ],
    [
        'short' => 's',
        'long' => 'show-tested',
        'value' => 'null',
        'optional' => true,
    ],
    [
        'short' => 'h',
        'long' => 'help',
        'value' => 'null',
        'optional' => true,
    ],
    [
        'short' => 'j',
        'long' => 'json-output',
        'value' => 'null',
        'optional' => true,
    ],
];
$parameter_convert = [
    'short_long' => array_combine(array_column($described_script_param, 'short'), array_column($described_script_param, 'long')),
    'long_short' => array_combine(array_column($described_script_param, 'long'), array_column($described_script_param, 'short')),
];

foreach ($parameter as $param => $value) {
    $convert_stl = $parameter_convert['short_long'][$param] ?? null;
    $convert_lts = $parameter_convert['long_short'][$param] ?? null;

    if (empty($convert_stl) && empty($convert_lts)) {
        echo 'Error: undefined parameter ' . $param . PHP_EOL;
        exit;
    }

    if (!empty($convert_stl) && !empty($convert_lts)) {
        echo 'FATAL ERROR IN $described_script_param ! PLEASE CONTACT CREATOR !' . PHP_EOL;
        exit;
    } else {
        $convert_long = $convert_stl ?? $param;
    }

    $description = array_filter($described_script_param,
    static function($x) use ($convert_long) {
        return $x['long'] === $convert_long;
    });
    $description = array_shift($description);

    if ($description['value'] === 'array' && (!is_array($value) || empty($value))) {
        $parameter[$param] = [];

        if (empty($parameter[$param])) {
            echo 'Error: parameter ' . $param . ' is empty' . PHP_EOL;
            exit;
        }
    }

    if ($description['value'] === 'string' && !is_array($value) && count($value) !== 1) {
        if (!is_array($value)) $parameter[$param] = [];
        if (count($value) !== 1) $parameter[$param] = array_shift($value);
    }

    if ($description['value'] === 'null' && $value !== true) {
        $parameter[$param] = true;
    }
}

echo 'Parsed !' . PHP_EOL;
echo 'Parameters: ' . implode(', ', array_keys($parameter)) . PHP_EOL;

if (isset($parameter['help']) || isset($parameter['h'])) {
    echo PHP_EOL;
    echo_help($described_script_param);
    exit;
}

$config_file = !empty($parameter['config']) ? $parameter['config'] : DEFAULT_CONSTS['CONFIG_FILE'];
$loader_file = !empty($parameter['load']) ? $parameter['load'] : DEFAULT_CONSTS['LOADER_FILE'];

echo PHP_EOL;
echo 'Config file' . (count($config_file) > 1 ? 's' : '') . ': ' . implode(' ', $config_file) . PHP_EOL;
echo 'Loader file' . (count($loader_file) > 1 ? 's' : '') . ': ' . implode(' ', $loader_file) . PHP_EOL;
echo PHP_EOL;

foreach ($config_file as $value) {
    if (!is_file($value)) {
        echo 'Error: ' . $value . ' is not valid' . PHP_EOL;
        exit;
    }

    require $value;
}

foreach ($loader_file as $value) {
    if (!is_file($value)) {
        echo 'Error: ' . $value . ' is not valid' . PHP_EOL;
        exit;
    }

    require $value;
}

if (!defined('EXTRACT_BLOCK_NB_LINE_AROUND')) define('EXTRACT_BLOCK_NB_LINE_AROUND', DEFAULT_CONSTS['EXTRACT_BLOCK_NB_LINE_AROUND']);
if (!defined('SOURCE_TESTS')) define('SOURCE_TESTS', DEFAULT_CONSTS['SOURCE_TESTS']);
if (!defined('BLACKLISTED_TESTS')) define('BLACKLISTED_TESTS', DEFAULT_CONSTS['BLACKLISTED_TESTS']);
if (!defined('WHITELISTED_TESTS')) define('WHITELISTED_TESTS', DEFAULT_CONSTS['WHITELISTED_TESTS']);

if (!file_exists(SOURCE_TESTS) || !is_dir(SOURCE_TESTS)) {
    echo 'ERROR, SOURCE_TESTS do not exist' . PHP_EOL;
    echo 'SOURCE_TESTS: ' . SOURCE_TESTS . PHP_EOL;
    exit;
}

$source = SOURCE_TESTS;
$paths = [$source];
$files = [];
$json_files = [];

$blacklist_enabled = !empty(BLACKLISTED_TESTS);
$whitelist_enabled = !empty(WHITELISTED_TESTS);

for ($i = 0; $i < count($paths); $i++) {
    $path = $paths[$i];
    $scan = scan_plus($path);

    foreach($scan as $a_scan) {
        $path_calc = substr($path . '/' . $a_scan, strlen($source) + 1);
        $path_calc_full = $source . '/' . $path_calc;

        if (is_link($path_calc_full)) continue;

        if (is_dir($path_calc_full)) {
            $paths[] = $path_calc_full;
        }

        if (is_file($path_calc_full)) {
            $files[] = $path_calc;
        }
    }
}

echo 'Blacklist ' . ($blacklist_enabled ? 'enabled' : 'disabled') . PHP_EOL;
echo 'Whitelist ' . ($blacklist_enabled ? 'enabled' : 'disabled') . PHP_EOL;
echo PHP_EOL;

echo 'Tests:' . PHP_EOL;

if (empty($files)) {
    echo 'Pas de test trouvé !' . PHP_EOL;
} else {
    foreach ($files as $value) {
        $expl = explode('/', $value);
        $basename = $expl[count($expl) - 1];

        if (
            $basename === 'load.php' ||
            $basename === 'init.php'
        ) continue;

        //$json_files[] = ['test' => implode('/', $expl), 'status' => $blacklisted];

        $blacklisted = false;
        $whitelisted = true;

        if ($blacklist_enabled) foreach ($expl as $value) if (in_array($value, BLACKLISTED_TESTS, true)) $blacklisted = true;
        if ($whitelist_enabled) foreach ($expl as $value) if (in_array($value, WHITELISTED_TESTS, true)) $whitelisted = false;

        //echo implode('/', $expl) . ($disabled ? ' (Disabled)' : '') . PHP_EOL;

        echo implode('/', $expl);

        if ($blacklist_enabled && $whitelist_enabled) {
            if ($blacklisted) {
                echo ' (Blasklisted)';
            } elseif ($whitelisted) {
                echo ' (Not Whitelisted)';
            }
        } elseif ($blacklist_enabled && $blacklisted) {
            echo ' (Blasklisted)';
        } elseif ($whitelist_enabled && $whitelisted) {
            echo ' (Not Whitelisted)';
        }

        echo PHP_EOL;
    }
}

echo 'OK !' . PHP_EOL;

var_dump($parameter);
var_dump($json_files);

if ($parameter['show-tested'] ?? false) {
    echo PHP_EOL;
    echo json_encode($json_files);

    exit;
}

echo PHP_EOL;

$loaded = [];
$json_return = [];

var_dump($files);

foreach ($files as $a_file) {
    $pinfo = pathinfo($a_file);
    $expl = explode('/', $a_file);

    var_dump([
        $a_file,
        $pinfo,
        $expl,
    ]);

    $blasklisted = null;
    if ($blacklist_enabled) {
        $blasklisted = false;
        foreach ($expl as $value) if (in_array($value, BLACKLISTED_TESTS, true)) $blasklisted = true;
    }

    $whitelisted = null;
    if ($whitelist_enabled) {
        $whitelisted = true;
        foreach ($expl as $value) if (in_array($value, WHITELISTED_TESTS, true)) $whitelisted = false;
    }

    var_dump([
        'blacklist_enabled' => $blacklist_enabled,
        'whitelist_enabled' => $whitelist_enabled,
        'blasklisted' => $blasklisted,
        'whitelisted' => $whitelisted,
    ]);

    if (
        $pinfo['basename'] === 'load.php' ||
        $pinfo['basename'] === 'init.php'
    ) continue;

    $loader_rec = $pinfo['dirname'] . '/load.php';
    $initer_rec = $pinfo['dirname'] . '/init.php';
    $init_array = [];

    var_dump([
        'loader_rec' => $loader_rec,
        'initer_rec' => $initer_rec,
    ]);

    do {
        if (!in_array($loader_rec, $loaded, true) && is_file($source . '/' . $loader_rec)) {
            require $source . '/' . $loader_rec;
            $loaded[] = $loader_rec;
        }

        $loader_rec = explode('/', $loader_rec);
        array_pop($loader_rec); // - load.php
        array_pop($loader_rec); // - last dir
        $loader_rec[] = 'load.php';
        $loader_rec = implode('/', $loader_rec);
        usleep(100000);
    } while ($loader_rec !== '/load.php' && $loader_rec !== 'load.php');

    do {
        $init_array[] = $initer_rec;

        $initer_rec = explode('/', $initer_rec);
        array_pop($initer_rec); // - init.php
        array_pop($initer_rec); // - last dir
        $initer_rec[] = 'init.php';
        $initer_rec = implode('/', $initer_rec);
    } while ($initer_rec !== '/init.php' && $initer_rec !== 'init.php');

    $init_array[] = 'init.php';
    $init_array = array_reverse($init_array);

    foreach ($init_array as $an_init_file) {
        $full_path = $source . '/' . $an_init_file;

        if (!is_file($full_path)) continue;

        $init_initied[] = $full_path;
        require $full_path;
    }

    if ($blacklist_enabled && !$blasklisted) {
        $Testeur = new Testeur();
    }

    echo 'Test: ' . $a_file . PHP_EOL;
    $fatal = false;
    echo "\t" . 'result: ';

    if (!$disabled) {
        try {
            include $source . '/' . $a_file;
        } catch (\Throwable $th) {
            $fatal = $th;
        }

        echo $Testeur->nombre_test() . ' ';
    }

    if ($disabled) {
        echo 'Disabled' . PHP_EOL;

        if (isset($parameter['json-output'])) {
            $json_return[$a_file] = 'DISABLED';
        }
    } elseif ($fatal !== false) {
        echo '/!\\ FATAL ERROR /!\\' . PHP_EOL;
        echo "\t" . 'message: ' . $th->getMessage() . PHP_EOL;
        echo "\t" . 'file n line: ' . $th->getFile() . ':' . $th->getLine() . PHP_EOL;

        if (isset($parameter['json-output'])) {
            $json_return[$a_file] = 'FATAL';
        }
    } elseif ($Testeur->nombre_test() === 0) {
        echo '/!\\ pas de test' . PHP_EOL;

        if (isset($parameter['json-output'])) {
            $json_return[$a_file] = 'NO_TEST';
        }
    } elseif ($Testeur->resultat_positif()) {
        echo 'OK' . PHP_EOL;

        if (isset($parameter['json-output'])) {
            $json_return[$a_file] = 'OK';
        }
    } else {
        echo PHP_EOL;
        $Testeur->display_resultats();

        if (isset($parameter['json-output'])) {
            $json_return[$a_file] = 'ERROR';
        }
    }

    echo PHP_EOL;
}

var_dump($json_return);

if (isset($parameter['json-output'])) {
    echo PHP_EOL;
    echo json_encode($json_return);
}
