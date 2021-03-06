<?php
/**
+----------------------------------------------------------------------
| swoolefy framework bases on swoole extension development, we can use it easily!
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| @see https://github.com/bingcool/swoolefy
+----------------------------------------------------------------------
*/

namespace Swoolefy\Core\Crontab;

use Cron\CronExpression;
use Swoolefy\Core\Application;
use Swoolefy\Core\BaseServer;
use Swoolefy\Core\Timer\TickManager;

class CrontabManager {

	use \Swoolefy\Core\SingletonTrait;

	protected $cron_tasks = [];

    /**
     * @param string $cron_name
     * @param string $expression
     * @param mixed  $func
     * @throws \Exception
     */
	public function addRule(string $cron_name, string $expression, $func) {
	    if(!class_exists('Cron\\CronExpression')) {
            throw new \Exception("If you want to use crontab, you need to install 'composer require dragonmantank/cron-expression' ");
        }

	    if(!CronExpression::isValidExpression($expression)) {
            throw new \Exception("Crontab expression format is wrong, please check it");
        }

        if(!is_callable($func)) {
            throw new \Exception("Params func must be callable");
        }

        $cron_name_key = md5($cron_name);

        if(isset($this->cron_tasks[$cron_name_key])) {
            throw new \Exception("Cron name=$cron_name had been setting, you can not set same name again!");
        }

        $this->cron_tasks[$cron_name_key] = [$expression, $func];

        if(is_array($func)) {
            list($class, $action) = $func;
            if(!is_subclass_of($class, '\\Swoolefy\\Core\\Crontab\\AbstractCronController')) {
                throw new \Exception(__CLASS__.__FUNCTION__." Params of func about Crontab Handle Controller need to extend Swoolefy\\Core\\Crontab\\AbstractCronController");
            }
            \Swoole\Timer::tick(1000, function($timer_id, $expression) use($class, $action, $cron_name) {
                try{
                    $cronInstance = new $class;
                    $cronInstance->{$action}($expression, null, $cron_name);
                }catch (\Throwable $throwable) {
                    BaseServer::catchException($throwable);
                }finally {
                    Application::removeApp($cronInstance->coroutine_id);
                }
            }, $expression);
        }else {
            \Swoole\Timer::tick(1000, function($timer_id, $expression) use($func, $cron_name) {
                try{
                    $cronInstance = new CronController();
                    $cronInstance->runCron($expression, $func, $cron_name);
                }catch (\Throwable $throwable) {
                    BaseServer::catchException($throwable);
                }finally {
                    Application::removeApp($cronInstance->coroutine_id);
                }
            }, $expression);
        }

        unset($cron_name_key);
	}

    /**
     * @param string|null $cron_name
     * @return array|mixed|null
     */
	public function getCronTaskByName(string $cron_name = null) {
		if($cron_name) {
			$cron_name_key = md5($cron_name);
			if(isset($this->cron_tasks[$cron_name_key])) {
				return $this->cron_tasks[$cron_name_key];
			}
			return null;
		}
		return $this->cron_tasks;
	}

}