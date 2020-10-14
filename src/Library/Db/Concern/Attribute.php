<?php
/**
 * +----------------------------------------------------------------------
 * | swoolefy framework bases on swoole extension development, we can use it easily!
 * +----------------------------------------------------------------------
 * | Licensed ( https://opensource.org/licenses/MIT )
 * +----------------------------------------------------------------------
 * | @see https://github.com/bingcool/swoolefy
 * +----------------------------------------------------------------------
 */

namespace Swoolefy\Library\Db\Concern;

trait Attribute
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 字段自动类型转换
     * @var array
     */
    protected $type = [];

    /**
     * 数据表废弃字段
     * @var array
     */
    protected $disuse = [];

    /**
     * 数据表只读字段
     * @var array
     */
    protected $readonly = [];

    /**
     * 当前模型数据
     * @var array
     */
    private $data = [];

    /**
     * afterUpdate后可以获取不同属性
     * @var array
     */
    private $diffAttributes = [];

    /**
     * 原始数据
     * @var array
     */
    private $origin = [];

    /**
     * 修改器执行记录
     * @var array
     */
    private $set = [];

    /**
     * 获取模型对象的主键
     * @return string
     */
    public function getPk()
    {
        return $this->pk;
    }

    /**
     * 判断一个字段名是否为主键字段
     * @param  string $key 名称
     * @return bool
     */
    protected function isPk(string $key): bool
    {
        $pk = $this->getPk();

        if (is_string($pk) && $pk == $key) {
            return true;
        } elseif (is_array($pk) && in_array($key, $pk)) {
            return true;
        }

        return false;
    }

    /**
     * 获取模型对象的主键值
     * @return mixed
     */
    public function getPkValue()
    {
        $pk = $this->getPk();
        if(is_string($pk) && array_key_exists($pk, $this->data)) {
            $types = $this->getFieldType();
            $pkValue = $this->data[$pk] ?? 0;
            if(isset($types[$pk]) && !empty($pkValue)) {
                if($types[$pk] == 'int' || $types[$pk] == 'integer') {
                    $pkValue = (int)$pkValue;
                }else if($types[$pk] == 'float') {
                    $pkValue = (float)$pkValue;
                }else {
                    $pkValue = (string)$pkValue;
                }
            }
            return $pkValue;
        }
        return 0;
    }

    /**
     * 设置允许写入的字段,默认获取数据表所有字段
     * @param  array $field 允许写入的字段
     * @return $this
     */
    public function allowField(array $field)
    {
        $this->tableFields = $field;
        return $this;
    }

    /**
     * 获取对象原始数据 如果不存在指定字段返回null
     * @param  string $fieldName 字段名 留空获取全部
     * @return mixed
     */
    public function getOrigin(string $fieldName = null)
    {
        if(is_null($fieldName)) {
            return $this->origin;
        }
        return $this->origin[$fieldName] ?? null;
    }

    /**
     * 获取对象原始数据(原始出表或者对象设置即将如表的数据) 如果不存在指定字段返回false
     * @param  string $fieldName 字段名 留空获取全部
     * @return mixed
     * @throws Exception
     */
    public function getData(string $fieldName = null)
    {
        if(is_null($fieldName)) {
            return $this->data;
        }
        return $this->data[$fieldName] ?? null;
    }

    /**
     * 获取变化的数据 并排除只读数据
     * @return array
     */
    protected function getChangeData(): array
    {
        $diffData = $this->force ? $this->data : $this->parseDiffData();
        return $diffData;
    }

    /**
     * @return array
     */
    protected function parseDiffData() {
        $diffData = array_udiff_assoc($this->data, $this->origin, function ($a, $b) {
            if ((empty($a) || empty($b)) && $a !== $b) {
                return 1;
            }

            return is_object($a) || $a != $b ? 1 : 0;
        });

        // 只读字段不允许更新
        foreach ($this->readonly as $key => $field) {
            if (isset($diffData[$field])) {
                unset($diffData[$field]);
            }
        }

        $originAttributes = $newAttributes = [];
        foreach($diffData as $fieldName=>$value) {
            $originValue = isset($this->origin[$fieldName]) ? $this->getValue($fieldName, $this->origin[$fieldName]) : null;
            $originAttributes[$fieldName] = $originValue;
            $newAttributes[$fieldName] = $this->getValue($fieldName, $value);
        }

        if($originAttributes) {
            $this->diffAttributes = [
                'old_attributes' => $originAttributes ?? [],
                'new_attributes' => $newAttributes ?? []
            ];
        }

        return $diffData;
    }

    /**
     * @return array
     */
    public function getDiffAttributes() {
        if($this->isNew()) {
            foreach($this->origin as $field=>$value) {
                $newAttributes[$field] = $this->getValue($field, $value);
            }
            $diffAttributes = [
                'old_attributes' => [],
                'new_attributes' => $newAttributes ?? []
            ];
        }else {
            if(empty($this->diffAttributes)) {
                $this->parseDiffData();
            }
            $diffAttributes = $this->diffAttributes;
        }

        return $diffAttributes;
    }

    /**
     * 获取指定字段更新值
     * @param array $customFields
     * @return array
     */
    protected function getCustomData(array $customFields): array
    {
        $diffData = $originAttributes = $newAttributes = [];
        foreach($customFields as $fieldName) {
            if(isset($this->readonly[$fieldName]) || !isset($this->data[$fieldName]) || !isset($this->origin[$fieldName])) {
                continue;
            }
            $diffData[$fieldName] = $this->data[$fieldName];
            $originAttributes[$fieldName] = $this->getValue($fieldName, $this->origin[$fieldName]);
            $newAttributes[$fieldName] = $this->getValue($fieldName, $this->data[$fieldName]);
        }

        if($originAttributes) {
            $this->diffAttributes = [
                'old_attributes' => $originAttributes ?? [],
                'new_attributes' => $newAttributes ?? []
            ];
        }

        return $diffData;
    }

    /**
     * 直接设置数据对象值
     * @param  string $name  属性名
     * @param  mixed  $value 值
     * @return void
     */
    public function set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * 数据写入 类型转换
     * @param  mixed        $value 值
     * @param  string|array $type  要转换的类型
     * @return mixed
     */
    protected function writeTransform($value, $type)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($type)) {
            [$type, $param] = $type;
        } elseif (strpos($type, ':')) {
            [$type, $param] = explode(':', $type, 2);
        }

        switch ($type) {
            case 'int':
            case 'integer':
                $value = (int) $value;
                break;
            case 'float':
                if (empty($param)) {
                    $value = (float) $value;
                } else {
                    $value = (float) number_format($value, (int) $param, '.', '');
                }
                break;
            case 'bool':
            case 'boolean':
                $value = (bool) $value;
                break;
            case 'timestamp':
                if (!is_numeric($value)) {
                    $value = strtotime($value);
                }
                break;
            case 'datetime':
                $value = is_numeric($value) ? $value : strtotime($value);
                $value = $this->formatDateTime('Y-m-d H:i:s', $value, true);
                break;
            case 'object':
                if (is_object($value)) {
                    $value = json_encode($value, JSON_FORCE_OBJECT);
                }
                break;
            case 'array':
                $value = (array) $value;
            case 'json':
                $option = !empty($param) ? (int) $param : JSON_UNESCAPED_UNICODE;
                $value  = json_encode($value, $option);
                break;
            case 'serialize':
                $value = serialize($value);
                break;
            default:
                if (is_object($value) && false !== strpos($type, '\\') && method_exists($value, '__toString')) {
                    // 对象类型
                    $value = $value->__toString();
                }
        }

        return $value;
    }

    /**
     * 数据读取 类型转换
     * @param  mixed        $value 值
     * @param  string|array $type  要转换的类型
     * @return mixed
     */
    protected function readTransform($value, $type)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($type)) {
            [$type, $param] = $type;
        } elseif (strpos($type, ':')) {
            [$type, $param] = explode(':', $type, 2);
        }

        switch ($type) {
            case 'int':
            case 'integer':
                $value = (int) $value;
                break;
            case 'float':
                if (empty($param)) {
                    $value = (float) $value;
                } else {
                    $value = (float) number_format($value, (int) $param, '.', '');
                }
                break;
            case 'bool':
            case 'boolean':
                $value = (bool) $value;
                break;
            case 'timestamp':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value  = $this->formatDateTime($format, $value, true);
                }
                break;
            case 'datetime':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value  = $this->formatDateTime($format, $value);
                }
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
            case 'array':
                $value = empty($value) ? [] : json_decode($value, true);
                break;
            case 'object':
                $value = empty($value) ? new \stdClass() : json_decode($value);
                break;
            case 'serialize':
                try {
                    $value = unserialize($value);
                } catch (\Exception $e) {
                    $value = null;
                }
                break;
            default:
                if (false !== strpos($type, '\\')) {
                    // 对象类型
                    $value = new $type($value);
                }
        }

        return $value;
    }
}
