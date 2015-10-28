<?php

namespace Cvuorinen\RaspiPHPant;

use Aura\Cli\Stdio;
use Cvuorinen\Raspicam\Raspistill;
use Twitter;
use TwitterException;

class RaspiPHPantCommand
{
    /**
     * Twitter char limit when tweet includes image
     */
    const CHAR_LIMIT = 116;

    /**
     * @var Stdio
     */
    private $stdio;

    /**
     * @var Twitter
     */
    private $twitter;

    /**
     * @var Raspistill
     */
    private $camera;

    /**
     * @var string
     */
    private $picsDirectory;

    /**
     * @var null|string
     */
    private $hashtag;

    /**
     * @var int ID of the last tweet that has been handled
     */
    private $lastTweetId;

    /**
     * @var string Username of the account the API tokens belong to
     */
    private $twitterUsername;

    /**
     * @param Stdio       $stdio
     * @param Twitter     $twitter
     * @param Raspistill  $camera
     * @param string      $picsDirectory
     * @param string|null $hashtag
     */
    public function __construct(
        Stdio $stdio,
        Twitter $twitter,
        Raspistill $camera,
        $picsDirectory,
        $hashtag = null
    ) {
        $this->stdio = $stdio;
        $this->twitter = $twitter;
        $this->camera = $camera;
        $this->picsDirectory = $picsDirectory;

        if (!empty($hashtag) && $hashtag{0} !== '#') {
            throw new \InvalidArgumentException(
                'Invalid hashtag "' . $hashtag . '". Hashtag must begin with # character.'
            );
        }

        $this->hashtag = $hashtag;
    }

    /**
     * @param int $interval
     */
    public function run($interval)
    {
        $this->verifyAccount();
        $this->checkLastReply();

        while (true) {
            sleep($interval);

            // load 20 latest replies sorted by newest first
            try {
                $replies = $this->twitter->load(Twitter::REPLIES);
            } catch (TwitterException $e) {
                $this->stdio->outln('Can\'t fetch new tweets at this time!');
                $this->stdio->outln('Error: ' . $e->getMessage());

                continue;
            }

            if (empty($replies)) {
                $this->stdio->outln("No new tweets to reply to");

                continue;
            }

            // reverse array to start with the ones that were sent earlier
            foreach (array_reverse($replies) as $tweet) {
                if ($this->shouldNotReplyTo($tweet)) {
                    continue;
                }

                $filename = $this->takePicture($tweet);

                $this->sendReply(
                    $tweet,
                    $filename
                );

                $this->lastTweetId = $tweet->id;
            }
        }
    }

    /**
     * Verify Twitter credentials and store our username
     */
    private function verifyAccount()
    {
        $account = $this->twitter->request('account/verify_credentials', 'GET');

        $this->twitterUsername = $account->screen_name;

        $this->stdio->outln('Account verified, running as "@' . $this->twitterUsername . '"');
    }

    /**
     * Store id of last reply so that we'll only handle tweets newer than this
     */
    private function checkLastReply()
    {
        $lastReply = $this->twitter->load(Twitter::REPLIES, 1);

        $this->lastTweetId = $lastReply[0]->id;

        $this->stdio->outln('Last reply ID "' . $this->lastTweetId . '"');
    }

    /**
     * @param \stdClass $tweet
     *
     * @return bool
     */
    private function shouldNotReplyTo(\stdClass $tweet)
    {
        // skip the ones already handled
        if ($tweet->id <= $this->lastTweetId) {
            return true;
        }

        // don't reply to ourself
        if ($tweet->user->screen_name == $this->twitterUsername) {
            return true;
        }

        // don't reply to replies (someone might comment on the pics etc.)
        if ($tweet->in_reply_to_status_id) {
            return true;
        }

        return false;
    }

    /**
     * @param \stdClass $tweet
     *
     * @return string
     */
    private function takePicture(\stdClass $tweet)
    {
        $filename = $this->picsDirectory . '/'
            . $tweet->id . '-' . $tweet->user->screen_name . '.jpg';

        // this call might throw exceptions but generally in that case it's ok to explode and dump stacktrace
        // since there's not much point to continue without the ability to take pictures
        $this->camera->takePicture($filename);

        $this->stdio->outln('Took picture with filename "' . $filename . '"');

        return $filename;
    }

    /**
     * @param \stdClass $tweet
     * @param string    $filename
     */
    private function sendReply(\stdClass $tweet, $filename)
    {
        try {
            $message = $this->createReplyMessage($tweet);

            $this->stdio->outln('Replying with message "' . $message . '"');

            $this->twitter->send(
                $message,
                $filename
            );

            $this->stdio->outln('Tweet sent!');
        } catch (TwitterException $e) {
            $this->stdio->outln('Failed to send tweet!');
            $this->stdio->outln('Error: ' . $e->getMessage());
        }
    }

    /**
     * @param \stdClass $tweet
     *
     * @return string
     */
    private function createReplyMessage(\stdClass $tweet)
    {
        $replyMessage = '.@' . $tweet->user->screen_name . ': ';

        // remove our own username
        $tweetText = trim(str_replace('@' . $this->twitterUsername, '', $tweet->text));

        // truncate original tweet text if our reply doesn't fit in char limit
        if (mb_strlen($replyMessage) + mb_strlen('"' . $tweetText . '"') > self::CHAR_LIMIT) {
            $length = self::CHAR_LIMIT - mb_strlen($replyMessage . '""...');
            $split = wordwrap($tweetText, $length, "\n");
            $tweetText = explode("\n", $split)[0] . '...';
        }

        $replyMessage .= '"' . $tweetText . '"';

        // add hashtag to message (if provided and not in the message already and fits within char limit)
        if (!empty($this->hashtag)
            && false === mb_stripos($replyMessage, $this->hashtag)
            && mb_strlen($replyMessage . ' ' . $this->hashtag) <= self::CHAR_LIMIT
        ) {
            $replyMessage .= ' ' . $this->hashtag;
        }

        return $replyMessage;
    }
}
