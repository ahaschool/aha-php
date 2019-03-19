<?php

namespace Aha\Commands;

use Illuminate\Console\Command;

class WikiCommand extends Command
{
    protected $signature = 'wiki{action}{--table=}';
    public $description = '代码生成swagger命令';

    public function handle()
    {
        $method = 'do' . ucfirst($this->argument('action'));
        $data = $this->$method();
        $this->info(json_encode($data));
    }

    public $swagger = [
        'swagger' => '2.0', 
        'info' => [
            'description' => '后台管理api',
            'title' => 'Mgr Api.',
            'version' => '0.0.1',
        ],
        'host' => 'mgrapi-dev.d.ahaschool.com',
        'schemes' => ['https'],
        'basePath' => '/',
        'tags' => [],
        'paths' => [],
        'definitions' => ['Error' => [
            'description' => '错误信息',
            'type' => 'object',
            'properties' => ['code' => ['type' => 'integer']]
        ]],
    ];
    public function doSwagger()
    {
        $swagger = &$this->swagger;
        $tags = [];
        $routes = [];
        $tables = [];
        $arr = glob(base_path('app/Http/Controllers/*/*Controller.php'));
        foreach ($arr as $value) {
            $name = ucfirst(strchr(strstr($value, 'app'), '.', TRUE));
            $class = '\\' . str_replace('/', '\\', $name);
            echo $class . PHP_EOL;
            $reflection = new \ReflectionClass($class);
            $doc = $reflection->getDocComment();
            if (stripos($doc, '@tag')) {
                $dict = $this->getDict($doc);
                $tags = array_merge($tags, array_get($dict, 'tags') ?: []);
            }
            $str = strchr(strchr($doc, '@'), "\n", true);
            $arr = explode(' ', $str);
            $tag = isset($arr[1]) ? $arr[1] : 'default';
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $doc = $method->getDocComment();
                if ($doc && (stripos($doc, '@method') || stripos($doc, '@api'))) {
                    $str = substr($doc, stripos($doc, '@api'), 10);
                    $str = strchr(strchr($doc, '@'), "\n", true);
                    $str = trim(strrchr($str, '/'), '/');
                    $arr = explode(',', $str);
                    foreach ($arr as $val) {
                        $txt = strtr($doc, [$str => $val]);
                        $routes[] = $this->getDict($txt) + ['group' => $tag];
                    }
                }
            }
        }
        $arr = array_column($tags, 'desc', 'name');
        foreach ($arr as $name => $desc) {
            $swagger['tags'][] = ['name' => $name, 'description' => $desc];
        }
        foreach ($routes as $item) {
            $class = $item['response_class'];
            if ($class && $class{0} != '{') {
                $ext = trim(strrchr($item['router'], '/'), '/');
                // 新版从注释读取类定义
                $model = self::getDefiProperty($class);
                $input = self::getTypeProperty($class, $ext);
            } else {
                $input = '';
                $model = '';
            }
            $request = [];
            if ($consumes = array_get($item, 'consumes')) {
                $request['consumes'] = $consumes;
            }
            // @see in 优先级第1 ，@source引用第二，@return 第三
            $parameters = array_get($item, 'parameters');
            if ($parameters && is_array($parameters)) {
                $request['parameters'] = isset($parameters[0]) ? $parameters : [[
                    'name' => 'Body',
                    'in' => 'body',
                    'required' => true,
                    'schema' => ['type' => 'object', 'properties' => $parameters],
                ]];
            } else if (is_array($source = array_get($item, 'request_link'))) {
                $request['parameters'] = isset($source[0]) ? $source : [[
                    'name' => 'Body',
                    'in' => 'body',
                    'required' => true,
                    'schema' => ['type' => 'object', 'properties' => $this->getType($source)],
                ]];
            } else if (is_string($source = array_get($item, 'request_link'))) {
                $value = json_decode($source, TRUE);
                $request['parameters'] = $source{0} == '{' ? [[
                    'name' => 'Body',
                    'in' => 'body',
                    'required' => true,
                    'schema' => ['type' => 'object', 'properties' => $value],
                ]] : ($source{0} == '[' ? $value : []);
            }
            $notget = strtolower($item['method']) != 'get';
            $swagger['paths'][$item['router']][$item['method']] = $request + [
                'summary' => $item['summary'],
                'tags' => array_filter(explode(',', array_get($item, 'group'))),
                'description' => array_get($item, 'brief') ?: $item['summary'],
                'consumes' => array_filter(array_get($request, 'consumes', [])),
                'parameters' => array_get($request, 'parameters') ?: ($notget ? [[
                    'description' => $input,
                    'name' => 'Body',
                    'in' => 'body',
                    'required' => true,
                    'schema' => ['$ref' => '#/definitions/' . $input]
                ]] : self::getQuery(array_merge(self::getProp($class, 'args', []), [
                    'page|integer|default:1|desc:分页页码',
                    'count|integer|default:10|desc:分页数量',
                ]))),
                'responses' => [200 => ['schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer'],
                        'message' => ['type' => 'string'],
                        'data' => array_get($item, 'responses') ?: ($item['response_type'] == 'array' ? [
                            'type' => 'array',
                            'items' => $class{0} == '{' ? [
                                'type' => 'object',
                                'properties' => json_decode($class, TRUE)
                            ] : ['$ref' => '#/definitions/' . $model]
                        ] : ($class{0} == '{' ? [
                            'type' => 'object',
                            'properties' => json_decode($class, TRUE)
                        ] : ['$ref' => '#/definitions/' . $model]))
                    ]
                ], 'description' => '返回信息']]
            ];
        }
        file_put_contents(storage_path('swagger.json'), json_encode($swagger));
        return 'ok';
    }

    // 通过输入参数定义
    public function getTypeProperty($class, $ext)
    {
        $class = trim($class, '\\');
        $alias = 'Input' . preg_replace('/[\\\]/','-', $class);
        $vars = self::getProp($class, 'rule', [
            'id' => 'int|desc:主键id',
            'ver' => 'int|desc:版本号',
        ]);
        if ($ext) {
            $maps = (self::getProp($class, 'maps') ?: []) + [
                'id' => 'get,update,status,updown',
                'status' => 'status',
                'sort' => 'updown',
            ];
            $arr = [];
            foreach ($maps as $key => $value) {
                if (in_array($ext, explode(',', $value))) {
                    $arr[] = $key;
                }
            }
            if (in_array($ext, ['create', 'update', 'upsert'])) {
                $diff = array_diff_key($vars, $maps);
                $maps = array_intersect_key($vars, array_flip($arr)) + $diff;
            } else {
                $maps = array_intersect_key($vars, array_flip($arr));
            }
        } else {
            $maps = $vars;
        }
        $data = [
            'description' => '',
            'type' => 'object',
            'properties' => self::getType($maps),
        ];
        self::formatDesc($data, substr($alias, 5));
        $this->swagger['definitions'][$alias] = $data;
        return $alias;
    }

    // 递归格式化描述
    public function formatDesc(&$data, $alias)
    {
        $dict = array_get($this->swagger['definitions'], $alias . '.properties');
        foreach ($data['properties'] as $key => $value) {
            if (!isset($dict[$key])) {
                continue;
            } else if ($value['type'] == 'array') {
                if ($ref = array_get($dict, $key . '.items.$ref')) {
                    self::formatDesc($value['items'], substr($ref, 14));
                    $data['properties'][$key]['items'] = $value['items'];
                }
            } else if ($value['type'] == 'object') {
                if ($ref = array_get($dict, $key . '.$ref')) {
                    self::formatDesc($value, substr($ref, 14));
                     $data['properties'][$key] = $value;
                }
            } else if (!array_get($value, 'description')) {
                $desc = array_get($dict, $key . '.description');
                if ($desc) {
                    $data['properties'][$key]['description'] =  $desc;
                }
            }
        }
    }

    // 获取类注释属性
    public function getDefiProperty($class)
    {
        $class = trim($class, '\\');
        $alias = preg_replace('/[\\\]/','-', $class);
        if (array_has($this->swagger['definitions'], $alias)) {
            return $alias;
        }
        $reflection = new \ReflectionClass($class);
        $doc = $reflection->getDocComment();
        preg_match_all('/@property(.+)/', $doc, $arr);
        $arr = isset($arr[1]) ? $arr[1] : [];
        $arr = preg_replace('/\s\s+/', ' ', $arr);
        foreach ($arr as $key => $value) {
            $arr[$key] = explode(' ', trim($value));
        }
        if ($doc) {
            $desc = trim(substr($doc, 6, stripos($doc, '*', 6) - 6));
        } else {
            $desc = $alias;
        }
        $instance = new $class;
        $properties = [];
        foreach ($arr as $val) {
            if (count($val) != 3) {
                continue;
            }
            list($type, $field, $desc) = $val;
            $properties[$field] = ['description' => $desc];
            $properties[$field]['type'] = $type;
            if ($type == 'array' || $type == 'object') {
                if (method_exists($instance, $field)) {
                    $relation = $instance->$field();
                    $related = get_class($relation->getRelated());
                    $name = self::getDefiProperty($related);
                    $ref = '#/definitions/' . $name;
                    if ($type == 'array') {
                        $properties[$field]['items']['$ref'] = $ref;
                    } else {
                        unset($properties[$field]['description']);
                        unset($properties[$field]['type']);
                        $properties[$field]['$ref'] = $ref;
                    }
                }
            }
        }
        $this->swagger['definitions'][$alias] = [
            'description' => $desc,
            'type' => 'object',
            'properties' => $properties ?: [
                'id' => ['type' => 'integer']
            ],
        ];
        return $alias;
    }

    // 获取类型，设置备注
    public function getType($arr, $class = '', $modelname = '')
    {
        $properties = [];
        foreach ($arr as $key => $value) {
            if (is_array($value) && isset($value[0])) {
                $properties[$key] = [
                    'type' => 'array',
                    'items' => is_array($value[0]) ? [
                        'type' => 'object',
                        'properties' => self::getType($value[0]),
                    ] : [
                        'type' => $value[0]{0} == 'i' ? 'integer' : 'string'
                    ],
                ];
                continue;
            } else if (is_array($value)) {
                $properties[$key] = [
                    'type' => 'object',
                    'properties' => self::getType($value),
                ];
                continue;
            } else if ($value == 'array') {
                continue;
            }
            $type = strchr($value . '|', '|', true);
            $type = $type == 'int' ? 'integer' : $type;
            $desc = explode(':', strstr($value, 'desc:') ?: ':')[1];
            $properties[$key] = [
                'description' => $desc,
                'type' => $type,
                'default' => $type == 'integer' ? 0 : '',
            ];
            $relation_matchs = [];
            preg_match('/relation:([^|]+)/', $value, $relation_matchs);
            if ($relation_matchs) {
                $name = $relation_matchs[1];
                $instance = new $class;
                $relation = $instance->$name();
                $related = get_class($relation->getRelated());
                $modelname = $this->getDefiProperty($related);
                $properties[$key] = $type == 'array' ? [
                    'description' => $modelname,
                    'type' => 'array',
                    'items' => ['$ref' => '#/definitions/' . $modelname]
                ] : ['$ref' => '#/definitions/' . $modelname];
            }
        }
        return $properties;
    }

    // 获取查询参数
    public function getQuery($obj)
    {
        $arr = [];
        foreach ($obj as $value) {
            preg_match('/default:([^|]+)/', $value, $default_matchs);
            $arr[] = [
                'description' => explode(':', strstr($value, 'desc:') ?: ':')[1],
                'name' => trim(strrchr(' ' . strchr($value . '|', '|', TRUE), ' ')),
                'type' => stripos($value, 'int') ? 'integer' : 'string',
                'in' => 'query',
                'required' => stripos($value, 'required') ? TRUE : FALSE,
                'default' => $default_matchs ? $default_matchs[1] : '',
            ];
        }
        return $arr;
    }

    // 主要用户获取属性，增加说明
    public function getProp($class, $name, $default = '')
    {
        if ('rule' == $name) {
            $default = method_exists($class, 'rules')
            && !empty($class::rules()) ? $class::rules() : $default;
        }
        $result = property_exists($class, $name) && !empty($class::${$name}) ? $class::${$name} : $default;
        if ($name == 'rule' && $result) {
            $type = property_exists($class, 'type') ? $class::$type : [];
            $dict = [];
            foreach ($result as $key => $value) {
                if (is_numeric($key)) {
                    $key = $value;
                    $value = '';
                }
                $str = array_get($type, $key);
                if ($str) {
                    if (!$value) {
                        $value = strchr($str . '|', '|', true);
                    }
                    if (!stripos($value, '|desc:') && stripos($str, '|desc:')) {
                        $value .= strstr($str, '|desc:');
                    }
                } else if (!$value) {
                    $value = 'string';
                }
                $key = str_replace('.*', '.0', $key);
                array_set($dict, $key, $value);
            }
            $result = $dict;
        } else if ($name == 'args' && $result) {
            $type = property_exists($class, 'type') ? $class::$type : [];
            foreach ($result as $key => $value) {
                $name = trim(strrchr(' ' . strchr($value . '|', '|', TRUE), ' '));
                $str = array_get($type, $name);
                if (!stripos($value, '|desc:') && stripos($str, '|desc:')) {
                    $result[$key] .= strstr($str, '|desc:');
                }
            }
        }
        return $result;
    }

    // 获取注释字典
    public function getDict($doc)
    {
        $arr = explode('*', substr($doc, stripos($doc, '* ') + 2));
        $dict = ['summary' => trim($arr[0]), 'see' => []] + array_fill_keys([
            'method', 'router', 'response_type', 'response_class'
        ], '') + ['parameters' => [], 'input' => [], 'tags' => []];
        foreach ($arr as $key => $value) {
            $arr[$key] = trim($value);
            if (stripos($value, '@')) {
                list($tag, $name, $value) = explode(' ', $arr[$key] . '  ');
                if ($tag == '@api') {
                    $dict['method'] = $name;
                    $dict['router'] = $value;
                } else if ($tag == '@see') {
                    $has = isset($dict['see'][$name]);
                    $origin = $has ? $dict['see'][$name] : '';
                    $dict['see'][$name] = $origin . $value;
                } else if ($tag == '@param') {
                    $dict['request_type'] = $name;
                    $dict['request_class'] = $value;
                } else if ($tag == '@return') {
                    $dict['response_type'] = $name;
                    $dict['response_class'] = $value;
                } else if ($tag == '@tag') {
                    $dict['tags'][] = ['name' => $name, 'desc' => $value];
                } else if ($tag == '@internal' && $name == 'consumes') {
                    $dict['consumes'] = [$value];
                } else if ($tag == '@internal' && $name == 'parameters') {
                    $dict['parameters'] = json_decode($value, TRUE);
                } else if ($tag == '@internal' && stripos($name, '.')) {
                    array_set($dict, $name, json_decode($value, TRUE));
                } else if ($tag == '@source' && $name == 'input') {
                    if ($value{0} == '\\' && defined($value)) {
                        $dict['request_link'] = constant($value);
                    } else if ($value{0} == '{') {
                        $result = json_decode($value, TRUE);
                        $name = array_pull($result, 'name');
                        $name = str_replace('.', '.properties.', $name);
                        array_set($dict, 'input.' . $name, $result);
                    }
                } else if ($tag == '@source' && $name == 'query') {
                    if ($value{0} == '{') {
                        $dict['parameters'][] = json_decode($value, TRUE);
                    }
                } else if ($tag == '@source' && $name == 'param') {
                    $origin = array_get($dict, 'parameters', []);
                    $result = json_decode($value, TRUE);
                    if (stripos($value, ':{') > 0) {
                        $dict['parameters'] = $result;
                    } else if ($value{0} == '{') {
                        $dict['parameters'] = array_merge($origin, $result);
                    }
                }
            }
        }
        // 重置描述
        $ext = trim(strrchr($dict['router'], '/'), '/');
        foreach ($dict['see'] as $key => $value) {
            list($type, $action) = explode('-', $key . '-' . $ext);
            if ($type == 'in' || $type == 'out') {
                $result = [];
                $items = json_decode(strtr('[' . $value . ']', [
                    '}{' => '},{',
                    '"desc"' => '"description"'
                ]), true);
                foreach ($items as $row) {
                    if ($dict['method'] == 'get' && $type == 'in') {
                        if (!isset($row['in'])) {
                            $row['in'] = 'query';
                        }
                        $result[] = $row;
                    } else {
                        $name = array_pull($row, 'name');
                        $name = str_replace('.', '.properties.', $name);
                        array_set($result, $name, $row);
                    }
                }
                $dict['see'][$type . '-' . $action] = $result;
            }
        }
        // see in 覆盖
        if ($input = array_get($dict, 'see.in-' . $ext)) {
            $dict['parameters'] = $dict['method'] == 'get' ? $input : [[
                'name' => 'Body',
                'in' => 'body',
                'required' => true,
                'schema' => ['type' => 'object', 'properties' => $input]
            ]];
        }
        // see out 覆盖
        if ($resp = array_get($dict, 'see.out-' . $ext)) {
            $dict['responses'] = ['type' => 'object', 'properties' => $resp];
        }
        // see note 覆盖
        if ($breif = array_get($dict, 'see.note-' . $ext)) {
            $dict['brief'] = $breif;
        }
        $txt = array_get([
            'get' => '详情',
            'create' => '创建',
            'update' => '更新',
            'status' => '状态',
            'updown' => '排序',
        ], $ext);
        if ($txt) {
            $dict['summary'] .= $txt;
        }
        return $dict;
    }

    public function doModel()
    {
        $table = $this->option('table');
        $tables = explode(',', $table);
        $sql = 'Select table_name, COLUMN_NAME, COLUMN_DEFAULT, DATA_TYPE, COLUMN_COMMENT from INFORMATION_SCHEMA.COLUMNS Where table_name in (' . implode(',', array_fill(0, count($tables), '?')) . ')';
        $struct = [];
        $items = \DB::select($sql, array_values($tables));
        foreach ($items as $item) {
            $str = 'string';
            switch ($item->DATA_TYPE) {
                case 'int':
                case 'bigint':
                case 'tinyint':
                    $str = 'int';
                    break;
                case 'text':
                case 'varchar':
                    $str = 'string';
                    break;
                case 'datetime':
                case 'timestamp':
                    $str = 'string';
                    break;
                default:
                    $str = 'string';
                    break;
            }
            if ($desc = $item->COLUMN_COMMENT) {
                $str .= '|desc:' . $desc;
            } else if ($item->COLUMN_NAME == 'id') {
                $str .= '|desc:主键id';
            } else if (in_array($item->COLUMN_NAME, ['created_at', 'updated_at'])) {
                continue;
            }
            array_set($struct, $item->table_name . '.' . $item->COLUMN_NAME, $str);
        }
        $arr = [];
        foreach ($struct as $type => $dict) {
            $arr[] = $type;
            $arr[] = '    public static $type = [';
            foreach ($dict as $key => $value) {
                $arr[] = sprintf('        \'%s\' => \'%s\',', $key, $value);
            }
            $arr[] = '    ];';
        }
        echo implode(PHP_EOL, $arr) . PHP_EOL;
        return 'ok';
    }
}