<?php
namespace bingher\tests;

use PHPUnit\Framework\TestCase;

class Base extends TestCase
{
    /**
     * 方法调用
     *
     * @param mixed  $class      类名或类实例
     * @param string $methodName 方法名
     * @param array  $args       传参
     *
     * @return mixed
     */
    protected function call($class, string $methodName, array $args = [])
    {
        if (is_string($class)) {
            $class    = new \ReflectionClass($class);
            $instance = $class->newInstance();
        } else {
            $instance = $class;
        }

        $method = new \ReflectionMethod($instance, $methodName);
        $method->setAccessible(true);
        if (empty($args)) {
            return $method->invoke($instance);
        } else {
            return $method->invokeArgs($instance, $args);
        }
    }

    /**
     * 类属性
     *
     * @param mixed  $class 类名或类实例
     * @param string $key   属性键名
     * @param mixed  $value 属性值,默认null,如果null表示获取属性值
     *
     * @return mixed
     */
    protected function prop($class, string $key, $value = null)
    {
        if (is_string($class)) {
            $class    = new \ReflectionClass($class);
            $instance = $class->newInstance();
        } else {
            $instance = $class;
        }
        $prop = new \ReflectionProperty($class, $key);
        $prop->setAccessible(true);
        if (is_null($value)) {
            return $prop->getValue($instance);
        } else {
            return $prop->setValue($instance, $value);
        }
    }
}
