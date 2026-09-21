<?php

namespace Tests\Feature;

use App\Jobs\SendReminderPushJob;
use App\Models\Device;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use App\Services\Push\PushDriver;
use App\Services\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function reminderFor(User $user, array $attributes = []): Reminder
    {
        $task = Task::create(['user_id' => $user->id, 'title' => 'Task', 'type' => 'other']);

        return Reminder::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'scheduled_for' => now()->subMinute(),
            'message' => 'Do the thing',
            'repeat' => 'none',
            'is_enabled' => true,
            'is_dispatched' => false,
            ...$attributes,
        ]);
    }

    private function fakePushDriver(): PushDriver
    {
        $driver = new class implements PushDriver
        {
            public array $sent = [];

            public function send(string $token, string $title, string $body, array $data = []): bool
            {
                $this->sent[] = ['token' => $token, 'title' => $title, 'body' => $body, 'data' => $data];

                return true;
            }
        };

        $this->app->instance(PushDriver::class, $driver);

        return $driver;
    }

    public function test_command_queues_due_reminders_and_marks_them_dispatched(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $reminder = $this->reminderFor($user);

        $this->artisan('reminders:dispatch-due')->assertSuccessful();

        Queue::assertPushed(
            SendReminderPushJob::class,
            fn (SendReminderPushJob $job) => $job->reminder->id === $reminder->id,
        );

        $reminder->refresh();
        $this->assertTrue($reminder->is_dispatched);
        $this->assertNotNull($reminder->dispatched_at);
    }

    public function test_command_ignores_future_disabled_and_already_dispatched_reminders(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->reminderFor($user, ['scheduled_for' => now()->addHour()]);
        $this->reminderFor($user, ['is_enabled' => false]);
        $this->reminderFor($user, ['is_dispatched' => true, 'dispatched_at' => now()]);

        $this->artisan('reminders:dispatch-due')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_job_sends_notification_and_reschedules_repeating_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $reminder = $this->reminderFor($user, ['repeat' => 'daily', 'scheduled_for' => now()->subMinute()]);
        $originalScheduledFor = $reminder->scheduled_for->copy();

        (new SendReminderPushJob($reminder))->handle();

        Notification::assertSentTo($user, TaskReminderNotification::class);

        $reminder->refresh();
        $this->assertFalse($reminder->is_dispatched);
        $this->assertTrue($reminder->scheduled_for->greaterThan($originalScheduledFor));
    }

    public function test_job_does_not_reschedule_one_time_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $reminder = $this->reminderFor($user, [
            'is_dispatched' => true,
            'dispatched_at' => now(),
        ]);
        $originalScheduledFor = $reminder->scheduled_for->toIso8601String();

        (new SendReminderPushJob($reminder))->handle();

        $reminder->refresh();
        $this->assertTrue($reminder->is_dispatched);
        $this->assertSame($originalScheduledFor, $reminder->scheduled_for->toIso8601String());
    }

    public function test_push_service_sends_to_each_device_token(): void
    {
        $user = User::factory()->create();
        Device::create(['user_id' => $user->id, 'device_id' => 'dev_1', 'fcm_token' => 'token-1']);
        Device::create(['user_id' => $user->id, 'device_id' => 'dev_2', 'fcm_token' => 'token-2']);
        Device::create(['user_id' => $user->id, 'device_id' => 'dev_3']);

        $driver = $this->fakePushDriver();

        $count = app(PushService::class)->sendToUser($user, 'Title', 'Body', ['taskId' => 'x']);

        $this->assertSame(2, $count);
        $this->assertSame(['token-1', 'token-2'], array_column($driver->sent, 'token'));
    }

    public function test_notification_writes_to_database_and_pushes_to_devices(): void
    {
        $user = User::factory()->create();
        Device::create(['user_id' => $user->id, 'device_id' => 'dev_1', 'fcm_token' => 'token-1']);

        $driver = $this->fakePushDriver();

        $reminder = $this->reminderFor($user);
        $user->notify(new TaskReminderNotification($reminder));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
        ]);

        $this->assertCount(1, $driver->sent);
        $this->assertSame('token-1', $driver->sent[0]['token']);
        $this->assertSame('Do the thing', $driver->sent[0]['body']);
    }
}
