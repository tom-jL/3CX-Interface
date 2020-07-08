<?php

namespace App\Http\Controllers;

use App\Contact;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class ContactController extends Controller
{

    public function createZenuContact($zenuContact){
        if($zenuContact->phone->work || $zenuContact->phone->mobile || $zenuContact->phone->home) {
            $contact = Contact::updateOrCreate([
                'zenu_id' => $zenuContact->id], [
                'zenu_id' => $zenuContact->id,
                'phone' => preg_replace('/\D+/', '', $zenuContact->phone->work ?: $zenuContact->phone->home),
                'mobile' => preg_replace('/\D+/', '', $zenuContact->phone->mobile),
                'first_name' => $zenuContact->first_name,
                'last_name' => $zenuContact->last_name,
                'company' => isset($zenuContact->company->name) ?: 'null',
                'type' => implode(', ',$zenuContact->types),
            ]);
            return $contact;
        }
        return '';
    }

    public function getZenuContactbyID($id){
        $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1').'/contacts/'.$id;
        $response = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false))
            ->get($uri);
        if($response->successful()){
            return json_decode($response->body())->data;
        }
        return false;
    }

    public function updateZenuContacts(){
        //Get the last time this request was made.
        $last_request = DB::table('requests')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->first();
        $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1') . '/contacts';
        if($last_request) {
            $carbon_date = Carbon::parse($last_request->created_at)->addHours(10);//->setTimezone('Australia/Brisbane');
            $timestamp = str_replace(' ','T',$carbon_date);//->format('c'); //format for ISO 8601 datetime standard
            Log::info($timestamp);
            $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1') . '/contacts?filter[last_modified_from]=' .$timestamp; //get all records since last update
        }
        $client = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false));
        $response = $client->get($uri);
        if($response->successful()) {
//            var_dump(json_decode($response->body()));
            $page = 1;
            $total_pages = json_decode($response->body())->pagination->total_pages;
            if (App::environment('local')) {
                $total_pages = 30; // TODO: Remove this for live.
            }
            while($page <= $total_pages) {
                $arr = json_decode($response->body())->data;
                foreach ($arr as $contact) {
                    $this->createZenuContact($contact);
                }
                $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1').'/contacts?page[number]='.$page++;
//                Log::info($uri);
                $response = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false))
                    ->get($uri);
            }
            \App\Request::create(['desc' => 'zenu']);
        }
    }

    public function showAllContacts()
    {
        return response()->json(Contact::all());
    }

    public function showZenuContacts($page)
    {
        if($page == 0) $page = 1;
        $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1').'/contacts?page[number]='.$page;
        $response = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false))
            ->get($uri);
        if($response->successful()){
            return json_decode($response->body())->data;
        }
        return false;
    }


    public function showOneContact($phone)
    {

        //First try get contact from local DB.
        $contact = DB::table('contacts')
            ->where('phone', substr($phone,3))
            ->orWhere('mobile', '0'.substr($phone,2))
            ->orderBy('updated_at','desc')
            ->get()
            ->first();
        if($contact) {
            //Update contact from Zenu API based on ID.
            $zenuContact = $this->getZenuContactbyID($contact->zenu_id);
            if($zenuContact->id){
                $contact = $this->createZenuContact($zenuContact);
            }
            return response()->json($contact, 201);
        } else {
            $this->updateZenuContacts();
            $contact = DB::table('contacts')
                ->where('phone', substr($phone,3))
                ->orWhere('mobile', '0'.substr($phone,2))
                ->orderBy('updated_at','desc')
                ->get()
                ->first();
            if($contact) {
                return response()->json($contact, 201);
            }
        }
        return '';

    }

    public function updateAll(){
        $this->updateZenuContacts();
    }

    public function create(Request $request)
    {
        $this->validate($request, [
            'phone' => 'required',
            'mobile' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'company' => 'required'
        ]);

        $contact = Contact::create($request->all());

        return response()->json($contact, 201);
    }

    public function update($id, Request $request)
    {
        $contact = Contact::findOrFail($id);
        $contact->update($request->all());

        return response()->json($contact, 200);
    }

    public function delete($id)
    {
        Contact::findOrFail($id)->delete();
        return response('Deleted Successfully', 200);
    }
}
