<?php

namespace App\Jobs;

use App\Contact;

class CreateContactJob extends Job
{

    private $contacts;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($contacts)
    {
        $this->contacts = $contacts;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->contacts as $contact) {
            if ($contact->phone->work || $contact->phone->mobile || $contact->phone->home) {
                Contact::updateOrCreate([
                    'zenu_id' => $contact->id], [
                    'zenu_id' => $contact->id,
                    'phone' => preg_replace('/\D+/', '', $contact->phone->work ?: $contact->phone->home),
                    'mobile' => preg_replace('/\D+/', '', $contact->phone->mobile),
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'company' => isset($contact->company->name) ?: 'null',
                    'type' => implode(', ', $contact->types),
                ]);
                return $contact;
            }
        }
    }
}
