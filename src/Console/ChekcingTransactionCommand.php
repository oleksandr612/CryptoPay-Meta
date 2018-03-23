<?php

namespace Hexters\CoinPayment\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Hexters\CoinPayment\Entities\cointpayment_log_trx as logs;
use Carbon\Carbon;
use CoinPayment;

class chekcingTransactionCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'coinpayment:transaction-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checking transaction refund';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {

        // Parameter hook
        $this->info('======================= CHECKING STARTING =======================');
        logs::whereIn('status', [0, 1])->where('expired', '>', Carbon::now())
          ->chunk(100, function($transactions){
            foreach($transactions as $trx){
              $this->info('Payment ID: ' . $trx->payment_id);
              $check = CoinPayment::api_call('get_tx_info', [
                'txid' => $trx->payment_id
              ]);
              if($check['error'] == 'ok'){
                $data = $check['result'];
                if($data['status'] > 0){
                  $trx->update([
                    'status_text' => $data['status_text'],
                    'status' => $data['status'],
                    'confirmation_at' => ((INT)$data['status'] === 100) ? date('Y-m-d H:i:s', $data['time_completed']) : null
                  ]);

                  $this->info('Status : ' . $data['status_text']);

                  // Send hook
                  $this->info('======================= SENDING WEBHOOK =======================');
                  $query = http_build_query($data);
                  $client = new \GuzzleHttp\Client();
                  $client->request('GET', route('coinpayment.webhook') . '?' . $query);
                }
              }
            }
          });
    }
}
