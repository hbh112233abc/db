<?php
declare(strict_types=1);

namespace bingher\db\connector;

use PDO;
use think\db\PDOConnection;

/**
 * OpenGauss数据库驱动.
 */
class OpenGauss extends PDOConnection
{

    /**
     * 默认PDO连接参数.
     *
     * @var array
     */
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    /**
     * 数据库归属用户
     * @var string
     */
    protected $owner = '';

    /**
     * 数据库模式
     * @var string
     */
    protected $schema = '';

    function __construct(array $config)
    {
        parent::__construct($config);
        $this->owner  = $config['username'];
        $this->schema = $config['schema'] ?: 'public';
    }

    static function pgType(string $type): string
    {
        switch ($type) {
            case 'int8':
                $type = 'bigint';
                break;
            case 'int4':
                $type = 'integer';
                break;
            case 'int2':
                $type = 'smallint';
                break;
            case 'bpchar':
                $type = 'char';
                break;
        }
        return $type;
    }

    /**
     * 解析pdo连接的dsn信息.
     *
     * @param array $config 连接信息
     *
     * @return string
     */
    protected function parseDsn(array $config): string
    {
        $dsn = 'pgsql:dbname=' . $config['database'] . ';host=' . $config['hostname'];

        if (!empty($config['hostport'])) {
            $dsn .= ';port=' . $config['hostport'];
        }
        $dsn .= ';options=--search_path=' . $this->schema;

        return $dsn;
    }

    /**
     * 取得数据表的字段信息.
     *
     * @param string $tableName
     *
     * @return array
     */
    public function getFields(string $tableName): array
    {
        [$tableName] = explode(' ', $tableName);

        $sql = <<<EOF
        SELECT
            a.attnum,
            a.attname AS field,
            e.typname AS type,
            a.attnotnull AS null,
            pg_get_expr(b.adbin, b.adrelid) AS default,
            c.description AS column_comment,
            d.contype AS key
        FROM
            pg_catalog.pg_attribute a
        LEFT JOIN
            pg_catalog.pg_attrdef b ON a.attrelid = b.adrelid AND a.attnum = b.adnum
        LEFT JOIN
            pg_catalog.pg_description c ON c.objoid = a.attrelid AND c.objsubid = a.attnum
        LEFT JOIN
            pg_catalog.pg_constraint d ON a.attrelid = d.conrelid AND a.attnum = ANY(d.conkey)
        LEFT JOIN pg_type e ON a.atttypid = e.oid
        WHERE
            a.attrelid = '$tableName'::regclass -- 替换为你的表名
            AND a.attnum > 0
            AND NOT a.attisdropped
        ORDER BY
            a.attnum;
        EOF;

        $logSql                      = $this->config['trigger_sql'];
        $this->config['trigger_sql'] = false;

        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        $this->config['trigger_sql'] = $logSql;
        $info                        = [];

        if (!empty($result)) {
            foreach ($result as $key => $val) {
                $val = array_change_key_case($val);

                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => static::pgType($val['type']),
                    'notnull' => (bool) $val['null'],
                    'default' => $val['default'],
                    'primary' => $val['key'] == 'p',
                    'autoinc' => str_starts_with((string) $val['default'], 'nextval('),
                ];
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息.
     *
     * @param string $dbName
     *
     * @return array
     */
    public function getTables(string $dbName = ''): array
    {
        $sql    = sprintf(
            "SELECT tablename AS Tables_in_test FROM pg_tables WHERE schemaname ='%s'",
            $this->schema,
        );
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_COLUMN);
        return $result;
    }

    protected function supportSavepoint(): bool
    {
        return true;
    }
}
