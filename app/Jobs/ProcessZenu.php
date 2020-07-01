<?php

namespace App\Jobs;


use App\Contact;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class ProcessZenu extends Job
{
    private $phone;

    /**
     * Create a new job instance.
     *
     * @param phonenumber
     * @return void
     */
    public function __construct($phone)
    {
        $this->phone = $phone;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1').'/contacts';
        $client = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false));
        $response = $client->get($uri);
        if($response->successful()){
            //var_dump(json_decode($response->body())->pagination->total_pages);
            $page = 0;
            $total_pages = json_decode($response->body())->pagination->total_pages;
            while($page <= $total_pages) {
                $arr = json_decode($response->body())->data;
                foreach ($arr as $contact) {
                    if (preg_replace('/\D+/', '', $contact->phone->work) == substr($this->phone, 3)) {
                        $this->createContact($contact);
                        return;
                    } elseif (preg_replace('/\D+/', '', $contact->phone->home) == substr($this->phone, 3)) {
                        $this->createContact($contact);
                        return;
                    } elseif (preg_replace('/\D+/', '', $contact->phone->mobile) == '0' . substr($this->phone, 2)) {
                        $this->createContact($contact);
                        return;
                    }
                }
                $client = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false));
                $response = $client->get($uri.'?page['.$page++.']');
                if(!$response->successful()){
                    return;
                }
            }
        }

    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        // Send user notification of failure, etc...
        Log::error($exception);
    }

    public function createContact($zenuContact){
        $contact = Contact::create([
            'zenu_id' => $zenuContact->id,
            'phone' => preg_replace('/\D+/','',isset($zenuContact->phone->work) ?: $zenuContact->phone->work),
            'mobile' => preg_replace('/\D+/','',$zenuContact->phone->mobile),
            'first_name' => $zenuContact->first_name,
            'last_name' => $zenuContact->last_name,
            'company' => isset($zenuContact->company->name) ?: 'null',
        ]);
    }

}
