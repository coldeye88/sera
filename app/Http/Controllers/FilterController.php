<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;

class FilterController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

        /**
     * @OA\Get(path="/api/denom",
     *     summary="Filter Array Denom",
     *     parameters={},
     *     @OA\Response(response="default", description="successful operation")
     * )
     */
    public function index()
    {
        $client = new Client;
        $res = $client->request('GET', 'https://gist.githubusercontent.com/Loetfi/fe38a350deeebeb6a92526f6762bd719/raw/9899cf13cc58adac0a65de91642f87c63979960d/filter-data.json');
        $data = json_decode($res->getBody(), true);
        $length = sizeof($data['data']['response']['billdetails']);        
        $denom = [];        
        for ($i=0; $i < $length; $i++) {
            $target = $data['data']['response']['billdetails'][$i]['body'][0];
            $explode = explode(':', $target);
            if ((int)$explode[1] >= 100000) {
                $denom[] .= trim($explode[1]);
            }            
        }
        print_r($denom);
    }        
}
