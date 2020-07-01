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

    public function getZenuContactbyID($id){
        $uri = env('ZENU_URI', 'https://api.zenu.com.au/api/v1').'/contacts/'.$id;
        $response = Http::withBasicAuth(env('ZENU_ID', false), env('ZENU_TOKEN',false))
            ->get($uri);
        if($response->successful()){
            return json_decode($response->body())->data;
        }
        return false;
    }

    public function showAllContacts()
    {
        return response()->json(Contact::all());
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
            var_dump($contact->id);
            if(isset($zenuContact->id)){
                $contact->update([
                    'zenu_id' => $zenuContact->id,
                    'phone' => preg_replace('/\D+/','', $zenuContact->phone->work),
                    'mobile' => preg_replace('/\D+/','', $zenuContact->phone->mobile),
                    'first_name' => $zenuContact->first_name,
                    'last_name' => 'tester',
                    //'type' => $zenuContact->type,
                    'company' => isset($zenuContact->company->name) ?: 'null',
                ]);
            }
            return response()->json($contact, 201);
        }

        //Try to get contact from Zenu API

//        Queue::push(new ProcessZenu($phone));

        $process = new ProcessZenu($phone);
        $process->handle();
        //$this->dispatch(new ProcessZenu($phone));//->chain([new ProcessProperty($phone)]);

//        //Try to get contact from PropertyMe API
//        $propContact = $this->getPropMeContact($phone);
//        if($propContact){
//            $contact = Contact::create([
//                'phone' => $propContact->ContactPerson->WorkPhone,
//                'mobile' => $propContact->ContactPerson->CellPhone,
//                'first_name' => $propContact->ContactPerson->FirstName,
//                'last_name' => $propContact->ContactPerson->LastName,
//                'company' => $propContact->TradeName,
//            ]);
//            return response()->json($contact, 201);
//        }
        return '';

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
