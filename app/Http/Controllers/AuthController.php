<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\SecurityScheme(
 *     type="http",
 *     description="Login with email and password to get the authentication token",
 *     name="Token based Based",
 *     in="header",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="apiAuth",
 * )
 */

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register', 'login']]);
    }

    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register user",
     *     operationId="RegisterUser",
     *     @OA\RequestBody(
     *         description="Register user object",
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
    public function register(Request $request)
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
     * @OA\Post(
     *     path="/api/login",
     *     summary="login user",
     *     operationId="loginUser",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *              example={
     *                  "email": "admin@admin.dev",
     *                  "password": "password"
     *              }
     *         )
     *     ),
     *     @OA\Response(response="default", description="successful operation"),
     *     @OA\Response(response="401", description="Unauthorized")     
     * )
     */
    public function login()
    {
        $credentials = request(['email', 'password']);        

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * @OA\Get(path="/api/me",
     *     summary="user",
     *     operationId="userInfo",
     *     security={{ "apiAuth": {} }}, 
     *     @OA\Response(response="default", description="successful operation"),
     *     @OA\Response(response="401", description="Unauthorized")     
     * )
     */
    public function me()
    {
        $user = auth()->user();
        return response()->json([
            'data' => $user            
        ]);
    }

    /**
     * @OA\Get(path="/api/logout",
     *     summary="user",
     *     operationId="userLogout",
     *     security={{ "apiAuth": {} }}, 
     *     @OA\Response(response="default", description="successful operation"),
     *     @OA\Response(response="401", description="Unauthorized")     
     * )
     */
    public function logout()
    {
        auth()->logout();
        return response()->json([
            'data' => 'Logout Successfully'
        ]);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ], Response::HTTP_OK);
    }
}
