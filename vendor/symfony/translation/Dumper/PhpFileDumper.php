<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

class PhpFileDumper extends FileDumper
{
	public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array())
	{
		return "<?php\n\nreturn " . var_export($messages->all($domain), true) . ";\n";
	}

	protected function getExtension()
	{
		return 'php';
	}
}

?>
