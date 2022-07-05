<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
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
     * @OA\Get(path="/api/users",
     *     summary="Index users",
     *     operationId="indexUser",
     *     parameters={},
     *     @OA\Response(response="default", description="successful operation")
     * )
     */
    public function index()
    {
        $users = User::all();
        return response()->json([
            'success' => true,
            'data' => $users
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Create user",
     *     operationId="createUser",
     *     @OA\RequestBody(
     *         description="Updated user object",
     *         required=true,
     *         @OA\JsonContent(
     *              example={
     *                  "name": "admin",
     *                  "email": "admin@admin.dev",
     *                  "password": "password"
     *              }
     *         )
     *     ),
     *     @OA\Response(response="default", description="successful operation")
     * )
     */
    public function store(Request $request)
    {        
        $validator = Validator::make($request->all(), [
            'name' => 'required | string ',
            'email' => 'required | email | unique:mongodb.users',
            'password' => 'required',
        ]);

        if ($validator->fails()) {            
            return response()->json([
                'error' => $validator->messages(), 
                'error_code' => Response::HTTP_BAD_REQUEST
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make('email');
        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Patch(
     *     path="/api/users/{id}",
     *     summary="Updated user",
     *     operationId="updateUser",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="_id that to be updated",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid user supplied"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(response="default", description="successful operation"),
     *     @OA\RequestBody(
     *         description="Updated user object",
     *         required=true,
     *         @OA\JsonContent(
     *              example={
     *                  "name": "admin",
     *                  "email": "admin@admin.dev",
     *                  "password": "password"
     *              }
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required | string ',
            'email' => 'required | email | unique:mongodb.users,email,'.$id.',_id',
            'password' => 'required',
        ]);

        if ($validator->fails()) {            
            return response()->json([
                'error' => $validator->messages(), 
                'error_code' => Response::HTTP_BAD_REQUEST
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = User::find($id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user
        ], Response::HTTP_OK);
    }
    
    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Delete user",     
     *     operationId="deleteUser",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The _id that needs to be deleted",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid username supplied",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *     ),
     *     @OA\Response(response="default", description="successful operation")
     * )
     */
    public function destroy($id)
    {
        $user = User::find($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'data' => 'User has been deleted'
        ], Response::HTTP_OK);
    }
}
