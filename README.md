# ahaschool
## 注意事项：
* 使用aha包时请使用绝对路径，如\Aha\Xxx:method(xxx)，方便查找管理  

## env配置示例
```
KAFKA_APP_BROKER=127.0.0.1
KAFKA_APP_PREFIX=
KAFKA_APP_TOPIC=

KAFKA_LOG_BROKER=127.0.0.1
KAFKA_LOG_PREFIX=
KAFKA_LOG_TOPIC=

QINIU_ACCESS_KEY=xxx
QINIU_SECRET_KEY=xxx
QINIU_DOMAIN=http://bucket.xxx.com

REDIS_API_HOST=127.0.0.1
REDIS_API_PORT=
REDIS_API_PASSWORD=
REDIS_API_DATABASE

REDIS_APP_HOST=127.0.0.1
REDIS_APP_PORT=
REDIS_APP_PASSWORD=
REDIS_APP_DATABASE
```


## wiki使用示例
```php
/**
 * 代码示例
 *
 * @tag name 中文名称
 */
class XxxController {
    /**
     * api接口1
     * 
     * @api get /api/get
     * @return array \Xxx\Xxx
     */
    public function getXxx(Request $request)
    {
        // todo...
    }

    /**
     * api接口2
     * 
     * @api post /api/create
     * @see in {"name":"name","type":"string","desc":""}
     * @see in {"name":"head","type":"object","desc":""}
     * @see in {"name":"head.avatar","type":"string","desc":""}
     * @see out {"name":"status","type":"integer","desc":""}
     */
    public function postXxx(Request $request)
    {
        // todo...
    }
}

class Xxx
{
    // 筛选
    public static $args = [
        'name regexp keyword|desc:关键字查询',
        'status',
    ];
    // 入参
    public static $rule = [
        'id' => 'int',
        'name' => 'string',
        'status' => 'int',
        'xxx.*.id' => 'int',
        'xxx.*.value' => 'string',
    ];
    // 定义
    public static $type = [
        'id' => 'int|desc:主键id',
        'name' => 'string|desc:名称',
        'status' => 'integer|desc:状态',
        'xxx' => 'array|relation:xxx|desc:xxx关系',
    ];
}

/**
 * api接口x
 * 
 * @api post /xxx/xxx/get,create,update,status,updown
 * @see note-get x详情，只用传id
 * @see note-create x创建，不可传id
 * @see note-update x更新，传id和数据
 * @see note-status x状态，id和status
 * @see note-updown x排序，id和sort上下移
 * @source input \Xxxx\Xxx::$xxx
 * @source param {}
 * @return array \Xxx\Xxx
 * @return object {"status":{"type":"integer"}}
 */
```