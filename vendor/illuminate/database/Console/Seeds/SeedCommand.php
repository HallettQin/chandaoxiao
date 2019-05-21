<?php
//cgxlm
namespace Illuminate\Database\Console\Seeds;

class SeedCommand extends \Illuminate\Console\Command
{
	use \Illuminate\Console\ConfirmableTrait;

	/**
     * The console command name.
     *
     * @var string
     */
	protected $name = 'db:seed';
	/**
     * The console command description.
     *
     * @var string
     */
	protected $description = 'Seed the database with records';
	/**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
	protected $resolver;

	public function __construct(\Illuminate\Database\ConnectionResolverInterface $resolver)
	{
		parent::__construct();
		$this->resolver = $resolver;
	}

	public function fire()
	{
		if (!$this->confirmToProceed()) {
			return NULL;
		}

		$this->resolver->setDefaultConnection($this->getDatabase());
		\Illuminate\Database\Eloquent\Model::unguarded(function() {
			$this->getSeeder()->__invoke();
		});
	}

	protected function getSeeder()
	{
		$class = $this->laravel->make($this->input->getOption('class'));
		return $class->setContainer($this->laravel)->setCommand($this);
	}

	protected function getDatabase()
	{
		$database = $this->input->getOption('database');
		return $database ?: $this->laravel['config']['database.default'];
	}

	protected function getOptions()
	{
		return array(
	array('class', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The class name of the root seeder', 'DatabaseSeeder'),
	array('database', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'The database connection to seed'),
	array('force', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Force the operation to run when in production.')
	);
	}
}

?>
