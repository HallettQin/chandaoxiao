<?php
//cgxlm
namespace Symfony\Component\Translation\Dumper;

class PoFileDumper extends FileDumper
{
	public function formatCatalogue(\Symfony\Component\Translation\MessageCatalogue $messages, $domain, array $options = array())
	{
		$output = 'msgid ""' . "\n";
		$output .= 'msgstr ""' . "\n";
		$output .= '"Content-Type: text/plain; charset=UTF-8\\n"' . "\n";
		$output .= '"Content-Transfer-Encoding: 8bit\\n"' . "\n";
		$output .= '"Language: ' . $messages->getLocale() . '\\n"' . "\n";
		$output .= "\n";
		$newLine = false;

		foreach ($messages->all($domain) as $source => $target) {
			if ($newLine) {
				$output .= "\n";
			}
			else {
				$newLine = true;
			}

			$output .= sprintf('msgid "%s"' . "\n", $this->escape($source));
			$output .= sprintf('msgstr "%s"', $this->escape($target));
		}

		return $output;
	}

	protected function getExtension()
	{
		return 'po';
	}

	private function escape($str)
	{
		return addcslashes($str, "\x00..\x1f\"\\");
	}
}

?>
