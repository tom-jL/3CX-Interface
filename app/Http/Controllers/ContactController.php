<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Jobs\ProcessProperty;
use App\Jobs\ProcessZenu;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;


class ContactController extends Controller
{

    public function updateZenuContact($zenuContact){
        $contact = Contact::updateOrCreate([
            'zenu_id' => $zenuContact->id],[
            'zenu_id' => $zenuContact->id,
            'phone' => preg_replace('/\D+/','',$zenuContact->phone->work),
            'mobile' => preg_replace('/\D+/','',$zenuContact->phone->mobile),
            'first_name' => $zenuContact->first_name,
            'last_name' => $zenuContact->last_name,
            'company' => isset($zenuContact->company->name) ?: 'null',
        ]);
        return $contact;
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
            $timestamp = $last_request->created_at->format('c'); //format for ISO 8601 datetime standard
            $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1') . '/contacts?filter[last_modified_from]=' .$timestamp; //get all records since last update
        }
        $client = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false));
        $response = $client->get($uri);
        if($response->successful()){
            //var_dump(json_decode($response->body())->pagination->total_pages);
            $page = 0;
            $total_pages = json_decode($response->body())->pagination->total_pages;
            $total_pages = 1; //for testing
            while($page <= $total_pages) {
                $arr = json_decode($response->body())->data;
                foreach ($arr as $contact) {
                    $this->updateZenuContact($contact);
                }
//                $client = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false));
                $response = $client->get($uri.'?page['.$page++.']');
                if(!$response->successful()){
                    break;
                }
            }
            DB::table('requests')->insert(['desc' => 'zenu']);
        }
    }

    public function showAllContacts()
    {
        return response()->json(Contact::all());
    }

    public function showZenuContacts($page)
    {
        $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1').'/contacts?page['.$page.']';
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
//            var_dump($contact->id);
            if(isset($zenuContact->id)){
                $contact = $this->updateZenuContact($zenuContact);
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
            return response()->json($contact, 201);
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
