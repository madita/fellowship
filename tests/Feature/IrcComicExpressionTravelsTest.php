<?php

namespace Tests\Feature;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcMessage;
use App\Models\Irc\IrcServer;
use App\Models\User;
use App\Services\Irc\IrcConnectionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * IRC carries no comic expression, and everyone keeps their own copy of a
 * channel's messages: the sender's copy is written by the API with the face
 * they picked, the copies for everyone else by the daemon. Without help the
 * others would all read as the plain default, so the sender's own line is
 * looked up and its expression carried over.
 */
class IrcComicExpressionTravelsTest extends TestCase
{
    use RefreshDatabase;

    protected IrcServer $server;

    protected IrcConnection $sender;

    protected IrcConnection $reader;

    protected IrcChannel $readerChannel;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::shouldReceive('get')->with('irc:daemon:heartbeat')->andReturn((string) time());
        Redis::shouldReceive('rpush')->andReturnTrue();

        $this->server = IrcServer::create([
            'name' => 'Test Net',
            'host' => 'irc.example.test',
            'port' => 6667,
        ]);

        $this->sender = $this->connectionFor('frodo');
        $this->reader = $this->connectionFor('samwise');

        // Each side has its own row for the same channel
        IrcChannel::create([
            'irc_connection_id' => $this->sender->id,
            'name'              => '#shire',
            'is_joined'         => true,
        ]);

        $this->readerChannel = IrcChannel::create([
            'irc_connection_id' => $this->reader->id,
            'name'              => '#shire',
            'is_joined'         => true,
        ]);
    }

    private function connectionFor(string $nickname, ?IrcServer $server = null): IrcConnection
    {
        return IrcConnection::create([
            'user_id'       => User::factory()->create()->id,
            'irc_server_id' => ($server ?? $this->server)->id,
            'nickname'      => $nickname,
            'status'        => 'connected',
        ]);
    }

    /**
     * The sender posts through the API, exactly as the client does.
     */
    private function send(array $payload): void
    {
        $channel = $this->sender->channels()->first();

        $this->actingAs($this->sender->user, 'sanctum')
            ->postJson("/api/irc/channels/{$channel->id}/messages", $payload)
            ->assertCreated();
    }

    /**
     * The line as it comes back off the wire for the other member.
     */
    private function deliver(string $from, string $text, ?IrcConnection $to = null): IrcMessage
    {
        $to = $to ?? $this->reader;

        (new IrcConnectionManager())->handleLine($to->id, ":{$from}!u@h PRIVMSG #shire :{$text}");

        return IrcMessage::where('irc_connection_id', $to->id)->latest('id')->firstOrFail();
    }

    public function test_the_face_the_sender_picked_reaches_the_other_member(): void
    {
        $this->send([
            'message'     => 'we should turn back',
            'emotion'     => 'sad',
            'gesture'     => 'think',
            'bubble_type' => 'thought',
        ]);

        $delivered = $this->deliver('frodo', 'we should turn back');

        $this->assertSame('sad', $delivered->emotion);
        $this->assertSame('think', $delivered->gesture);
        $this->assertSame('thought', $delivered->bubble_type);
    }

    public function test_a_shout_reaches_the_other_member_as_a_shout(): void
    {
        $this->send(['message' => 'run', 'emotion' => 'angry', 'gesture' => 'shout', 'bubble_type' => 'shout']);

        $delivered = $this->deliver('frodo', 'run');

        $this->assertSame('shout', $delivered->bubble_type);
        $this->assertSame('angry', $delivered->emotion);
    }

    public function test_someone_on_a_plain_irc_client_keeps_the_defaults(): void
    {
        $delivered = $this->deliver('stranger', 'hello everyone');

        $this->assertSame('normal', $delivered->emotion);
        $this->assertSame('none', $delivered->gesture);
        $this->assertSame('speech', $delivered->bubble_type);
    }

    public function test_a_nickname_on_another_server_is_not_borrowed_from(): void
    {
        $elsewhere = IrcServer::create(['name' => 'Other', 'host' => 'irc.other.test', 'port' => 6667]);
        $twin      = $this->connectionFor('frodo', $elsewhere);

        IrcChannel::create(['irc_connection_id' => $twin->id, 'name' => '#shire', 'is_joined' => true]);

        IrcMessage::create([
            'irc_channel_id'    => $twin->channels()->first()->id,
            'irc_connection_id' => $twin->id,
            'type'              => 'message',
            'from_nick'         => 'frodo',
            'message'           => 'a line from another network',
            'emotion'           => 'excited',
            'gesture'           => 'wave',
            'bubble_type'       => 'shout',
            'sent_at'           => now(),
        ]);

        $delivered = $this->deliver('frodo', 'a line from another network');

        $this->assertSame('normal', $delivered->emotion);
    }

    public function test_an_old_line_is_not_mistaken_for_the_one_just_sent(): void
    {
        IrcMessage::create([
            'irc_channel_id'    => $this->sender->channels()->first()->id,
            'irc_connection_id' => $this->sender->id,
            'type'              => 'message',
            'from_nick'         => 'frodo',
            'message'           => 'hello',
            'emotion'           => 'excited',
            'sent_at'           => now()->subMinutes(5),
        ]);

        $delivered = $this->deliver('frodo', 'hello');

        $this->assertSame('normal', $delivered->emotion);
    }

    public function test_a_private_message_carries_its_expression_too(): void
    {
        $this->actingAs($this->sender->user, 'sanctum')
            ->postJson("/api/irc/connections/{$this->sender->id}/pm", [
                'nick'        => 'samwise',
                'message'     => 'psst',
                'bubble_type' => 'whisper',
            ])
            ->assertOk();

        // The PM is stored under the sender's nick on the reader's side
        (new IrcConnectionManager())->handleLine(
            $this->reader->id,
            ':frodo!u@h PRIVMSG samwise :psst'
        );

        $delivered = IrcMessage::where('irc_connection_id', $this->reader->id)->latest('id')->firstOrFail();

        $this->assertSame('whisper', $delivered->bubble_type);
    }
}
