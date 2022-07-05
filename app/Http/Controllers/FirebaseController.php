<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirebaseController extends Controller
{
    protected $auth, $database;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $serviceAccount = ServiceAccount::fromJsonFile(__DIR__.'/config-firebase.json');
        $this->database = (new Factory)
        ->withServiceAccount($serviceAccount)
        ->withDatabaseUri('https://sera-c6c57-default-rtdb.asia-southeast1.firebasedatabase.app/')
        ->create();
    }

    /**
     * @OA\Get(path="/api/firebase",
     *     summary="Index Data",
     *     parameters={},
     *     @OA\Response(response="default", description="successful operation")
     * )
     */
    public function index()
    {        
        $database = $this->database->getDatabase();

        $ref = $database->getReference('products')->getSnapshot();
        $value = $ref->getValue();

        return response()->json([
            'data' => [
                'products' => $value
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/firebase/store",
     *     summary="Create data",
     *     @OA\RequestBody(
     *         description="Create data object",
     *         required=true,
     *         @OA\JsonContent(
     *              example={
     *                  "name": "admin"
     *              }
     *         )
     *     ),
     *     @OA\Response(response="default", description="successful operation")
     * )
     */
    public function store(Request $request)
    {        
        $name = $request->name;

        $postData = ["name" => $name];

        $database = $this->database->getDatabase();

        $database->getReference('products')->push($postData);
        
        $ref = $database->getReference('products')->getValue();

        return response()->json([
            'data' => [
                'products' => $ref
            ]
        ]);
    }
    
    /**
     * @OA\Patch(
     *     path="/api/firebase/update/{id}",
     *     summary="Updated data",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id that to be updated",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(response="default", description="successful operation"),
     *     @OA\RequestBody(
     *         description="Updated data object",
     *         required=true,
     *         @OA\JsonContent(
     *              example={
     *                  "name": "admin",
     *              }
     *         )
     *     )
     * )
     */    
    public function update(Request $request, $uid)
    {
        $name = $request->name;

        $postData = ["name" => $name];

        $database = $this->database->getDatabase();

        $updates = [
            'products/'.$uid => $postData,
        ];
        
        $database->getReference()->update($updates);

        $ref = $database->getReference('products')->getValue();

        return response()->json([
            'data' => [
                'products' => $ref
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/firebase/delete/{id}",
     *     summary="Delete data",   
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The _id that needs to be deleted",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(response="default", description="successful operation")
     * )
     */
    public function destroy($uid)
    {        
        $database = $this->database->getDatabase();
        
        $database->getReference('products/'.$uid)->remove();

        $ref = $database->getReference('products')->getValue();

        return response()->json([
            'data' => [
                'products' => $ref
            ]
        ]);
    }
}
