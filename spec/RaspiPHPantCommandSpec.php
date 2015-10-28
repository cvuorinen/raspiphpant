<?php

namespace Cvuorinen\RaspiPHPant\Spec;

use Cvuorinen\RaspiPHPant\RaspiPHPantCommand;
use kahlan\plugin\Stub;

describe("RaspiPHPantCommand", function () {

    beforeEach(function () {
        $this->stdio = Stub::create(['extends' => 'Aura\Cli\Stdio', 'magicMethods' => true]);
        Stub::on($this->stdio)->method('outln');

        $this->twitter = Stub::create(['extends' => 'Twitter', 'magicMethods' => true]);

        $this->camera = Stub::create(['extends' => 'Cvuorinen\Raspicam\Raspistill']);

        $this->picsDirectory = './pics';
        $this->hashtag = '#testing';
    });

    describe("__construct", function () {

        it("creates object when params are valid", function () {
            $command = new RaspiPHPantCommand(
                $this->stdio,
                $this->twitter,
                $this->camera,
                $this->picsDirectory,
                $this->hashtag
            );

            expect(is_object($command))->toBe(true);
        });

        it("throws exception when hashtag is invalid", function () {
            expect(function () {
                new RaspiPHPantCommand(
                    $this->stdio,
                    $this->twitter,
                    $this->camera,
                    $this->picsDirectory,
                    'foo'
                );
            })->toThrow(new \InvalidArgumentException());
        });

    });

    describe("->createReplyMessage", function () {

        beforeEach(function () {
            $this->command = new RaspiPHPantCommand(
                $this->stdio,
                $this->twitter,
                $this->camera,
                $this->picsDirectory,
                $this->hashtag
            );

            $this->username = 'McTesterson';

            // stub $this->command->twitterUsername
            Stub::on($this->command)
                ->method('__get')
                ->andReturn($this->username);

            $this->tweet = (object) [
                'user' => (object) [
                    'screen_name' => 'foo'
                ],
                'text' => 'tweet-text'
            ];
        });

        it("prefixes reply with dot and tweet sender username", function () {
            $reply = invokeMethod($this->command, 'createReplyMessage', [$this->tweet]);

            $expectedPrefix = '.@' . $this->tweet->user->screen_name;

            expect($reply)->toContain($expectedPrefix);
        });

        it("includes original tweet text in reply message", function () {
            $reply = invokeMethod($this->command, 'createReplyMessage', [$this->tweet]);

            expect($reply)->toContain($this->tweet->text);
        });

        it("removes own username from reply", function () {
            $this->tweet->text .= ' @' . $this->username;

            $reply = invokeMethod($this->command, 'createReplyMessage', [$this->tweet]);

            expect($reply)->not->toContain('@' . $this->username);
        });

        it("truncates too long messages without splitting words and suffix with ...", function () {
            $this->tweet->text .= ' ' . str_repeat("x", 140);

            $reply = invokeMethod($this->command, 'createReplyMessage', [$this->tweet]);

            expect(strlen($reply))->toBeLessThan(100);
            expect($reply)
                ->toContain('...')
                ->not->toContain('xxx');
        });

        it("appends hashtag to reply message", function () {
            $reply = invokeMethod($this->command, 'createReplyMessage', [$this->tweet]);

            expect($reply)->toContain($this->hashtag);
        });

        it("does not append hashtag to reply message when it already contains it", function () {
            $this->tweet->text .= ' ' . $this->hashtag;

            $reply = invokeMethod($this->command, 'createReplyMessage', [$this->tweet]);

            expect(substr_count($reply, $this->hashtag))->toBe(1);
        });

        it("does not append hashtag to reply message when it doesn't fit in char limit", function () {
            $this->tweet->text .= str_repeat(" xx", 50);

            $reply = invokeMethod($this->command, 'createReplyMessage', [$this->tweet]);

            expect($reply)->not->toContain($this->hashtag);
        });
    });

});

/**
 * Call protected/private method of a class.
 *
 * @param object &$object    Instantiated object that we will run method on.
 * @param string $methodName Method name to call
 * @param array  $parameters Array of parameters to pass into method.
 *
 * @return mixed Method return.
 */
function invokeMethod(&$object, $methodName, array $parameters = array())
{
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($object, $parameters);
}
