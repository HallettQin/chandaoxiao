<?php
//cgxlm
namespace Illuminate\Support\Testing\Fakes;

class NotificationFake implements \Illuminate\Contracts\Notifications\Factory
{
	/**
     * All of the notifications that have been sent.
     *
     * @var array
     */
	protected $notifications = array();

	public function assertSentTo($notifiable, $notification, $callback = NULL)
	{
		if (is_array($notifiable) || $notifiable instanceof \Illuminate\Support\Collection) {
			foreach ($notifiable as $singleNotifiable) {
				$this->assertSentTo($singleNotifiable, $notification, $callback);
			}

			return NULL;
		}

		\PHPUnit\Framework\Assert::assertTrue(0 < $this->sent($notifiable, $notification, $callback)->count(), 'The expected [' . $notification . '] notification was not sent.');
	}

	public function assertNotSentTo($notifiable, $notification, $callback = NULL)
	{
		if (is_array($notifiable) || $notifiable instanceof \Illuminate\Support\Collection) {
			foreach ($notifiable as $singleNotifiable) {
				$this->assertNotSentTo($singleNotifiable, $notification, $callback);
			}

			return NULL;
		}

		\PHPUnit\Framework\Assert::assertTrue($this->sent($notifiable, $notification, $callback)->count() === 0, 'The unexpected [' . $notification . '] notification was sent.');
	}

	public function sent($notifiable, $notification, $callback = NULL)
	{
		if (!$this->hasSent($notifiable, $notification)) {
			return collect();
		}

		$callback = $callback ?: function() {
			return true;
		};
		$notifications = collect($this->notificationsFor($notifiable, $notification));
		return $notifications->filter(function($arguments) use($callback) {
			return $callback(...array_values($arguments));
		})->pluck('notification');
	}

	public function hasSent($notifiable, $notification)
	{
		return !!$this->notificationsFor($notifiable, $notification);
	}

	protected function notificationsFor($notifiable, $notification)
	{
		if (isset($this->notifications[get_class($notifiable)][$notifiable->getKey()][$notification])) {
			return $this->notifications[get_class($notifiable)][$notifiable->getKey()][$notification];
		}

		return array();
	}

	public function send($notifiables, $notification)
	{
		return $this->sendNow($notifiables, $notification);
	}

	public function sendNow($notifiables, $notification)
	{
		if (!$notifiables instanceof \Illuminate\Support\Collection && !is_array($notifiables)) {
			$notifiables = array($notifiables);
		}

		foreach ($notifiables as $notifiable) {
			$notification->id = \Ramsey\Uuid\Uuid::uuid4()->toString();
			$this->notifications[get_class($notifiable)][$notifiable->getKey()][get_class($notification)][] = array('notification' => $notification, 'channels' => $notification->via($notifiable));
		}
	}

	public function channel($name = NULL)
	{
	}
}

?>
