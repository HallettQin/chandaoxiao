<?php
//cgxlm
namespace Illuminate\Support\Testing\Fakes;

class MailFake implements \Illuminate\Contracts\Mail\Mailer
{
	/**
     * All of the mailables that have been sent.
     *
     * @var array
     */
	protected $mailables = array();

	public function assertSent($mailable, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue(0 < $this->sent($mailable, $callback)->count(), 'The expected [' . $mailable . '] mailable was not sent.');
	}

	public function assertNotSent($mailable, $callback = NULL)
	{
		\PHPUnit\Framework\Assert::assertTrue($this->sent($mailable, $callback)->count() === 0, 'The unexpected [' . $mailable . '] mailable was sent.');
	}

	public function assertNothingSent()
	{
		\PHPUnit\Framework\Assert::assertEmpty($this->mailables, 'Mailables were sent unexpectedly.');
	}

	public function sent($mailable, $callback = NULL)
	{
		if (!$this->hasSent($mailable)) {
			return collect();
		}

		$callback = $callback ?: function() {
			return true;
		};
		return $this->mailablesOf($mailable)->filter(function($mailable) use($callback) {
			return $callback($mailable);
		});
	}

	public function hasSent($mailable)
	{
		return 0 < $this->mailablesOf($mailable)->count();
	}

	protected function mailablesOf($type)
	{
		return collect($this->mailables)->filter(function($mailable) use($type) {
			return $mailable instanceof $type;
		});
	}

	public function to($users)
	{
		return (new PendingMailFake($this))->to($users);
	}

	public function bcc($users)
	{
		return (new PendingMailFake($this))->bcc($users);
	}

	public function raw($text, $callback)
	{
	}

	public function send($view, array $data = array(), $callback = NULL)
	{
		if (!$view instanceof \Illuminate\Contracts\Mail\Mailable) {
			return NULL;
		}

		$this->mailables[] = $view;
	}

	public function queue($view, array $data = array(), $callback = NULL, $queue = NULL)
	{
		$this->send($view);
	}

	public function failures()
	{
	}
}

?>
