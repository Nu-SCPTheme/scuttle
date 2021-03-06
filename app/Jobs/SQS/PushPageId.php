<?php


namespace App\Jobs\SQS;

use App\Domain;
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;
use App\Wiki;

class PushPageId {

    public $callback_url;
    public $wd_site;
    public $page_id;

    public function __construct(int $page_id, int $wiki_id)
    {
        $wiki = Wiki::find($wiki_id);
        $domain = Domain::where('wiki_id',$wiki_id)->where('metadata->callback',true)->pluck('domain')->first();
        $this->callback_url = 'https://' . $domain . '/api';
        $metadata = json_decode($wiki->metadata, true);
        $this->wd_site = $metadata["wd_site"];

        $this->page_id = $page_id;
    }

    public function send(string $queue, string $fifostring = '')
    {
        $client = new SqsClient([
            'region' => env('SQS_REGION'),
            'version' => '2012-11-05',
            'credentials' => [
                'key' => env('SQS_KEY'),
                'secret' => env('SQS_SECRET')
            ]
        ]);

        $params = [
          'DelaySeconds' => 0,
          'MessageAttributes' =>  [
              'wikidot_site' => [
                  'DataType' => 'String',
                  'StringValue' => $this->wd_site
              ],
              'callback_url' => [
                  'DataType' => 'String',
                  'StringValue' => $this->callback_url
              ],
              'page_id' => [
                  'DataType' => 'Number',
                  'StringValue' => $this->page_id
              ]
          ],
            'MessageBody' => bin2hex(random_bytes(8)),
            'QueueUrl' => env('SQS_PREFIX') . '/' . $queue
        ];
        if(strlen($fifostring) > 0) {
            $params['MessageGroupId'] = $fifostring;
            $params['MessageDeduplicationId'] = bin2hex(random_bytes(64));
        }

        try {
            $result = $client->sendMessage($params);
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
        }

    }
}
