<?php
//cgxlm
namespace Illuminate\Database\Connectors;

class SqlServerConnector extends Connector implements ConnectorInterface
{
	/**
     * The PDO connection options.
     *
     * @var array
     */
	protected $options = array(\PDO::ATTR_CASE => \PDO::CASE_NATURAL, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL, \PDO::ATTR_STRINGIFY_FETCHES => false);

	public function connect(array $config)
	{
		$options = $this->getOptions($config);
		return $this->createConnection($this->getDsn($config), $config, $options);
	}

	protected function getDsn(array $config)
	{
		if (in_array('dblib', $this->getAvailableDrivers())) {
			return $this->getDblibDsn($config);
		}
		else if ($this->prefersOdbc($config)) {
			return $this->getOdbcDsn($config);
		}
		else {
			return $this->getSqlSrvDsn($config);
		}
	}

	protected function prefersOdbc(array $config)
	{
		return in_array('odbc', $this->getAvailableDrivers()) && (array_get($config, 'odbc') === true);
	}

	protected function getDblibDsn(array $config)
	{
		return $this->buildConnectString('dblib', array_merge(array('host' => $this->buildHostString($config, ':'), 'dbname' => $config['database']), \Illuminate\Support\Arr::only($config, array('appname', 'charset'))));
	}

	protected function getOdbcDsn(array $config)
	{
		return isset($config['odbc_datasource_name']) ? 'odbc:' . $config['odbc_datasource_name'] : '';
	}

	protected function getSqlSrvDsn(array $config)
	{
		$arguments = array('Server' => $this->buildHostString($config, ','));

		if (isset($config['database'])) {
			$arguments['Database'] = $config['database'];
		}

		if (isset($config['readonly'])) {
			$arguments['ApplicationIntent'] = 'ReadOnly';
		}

		if (isset($config['pooling']) && ($config['pooling'] === false)) {
			$arguments['ConnectionPooling'] = '0';
		}

		if (isset($config['appname'])) {
			$arguments['APP'] = $config['appname'];
		}

		if (isset($config['encrypt'])) {
			$arguments['Encrypt'] = $config['encrypt'];
		}

		if (isset($config['trust_server_certificate'])) {
			$arguments['TrustServerCertificate'] = $config['trust_server_certificate'];
		}

		return $this->buildConnectString('sqlsrv', $arguments);
	}

	protected function buildConnectString($driver, array $arguments)
	{
		return $driver . ':' . implode(';', array_map(function($key) use($arguments) {
			return sprintf('%s=%s', $key, $arguments[$key]);
		}, array_keys($arguments)));
	}

	protected function buildHostString(array $config, $separator)
	{
		if (isset($config['port']) && !empty($config['port'])) {
			return $config['host'] . $separator . $config['port'];
		}
		else {
			return $config['host'];
		}
	}

	protected function getAvailableDrivers()
	{
		return \PDO::getAvailableDrivers();
	}
}

?>
