<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\UserRepository;
use Illuminate\Http\Request;

class AccessTokenController extends Controller
{
    /**
     * Instance of UserRepository
     *
     * @var UserRepository
     */
    private $userRepository;

    /**
     * Constructor
     *
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;

        parent::__construct();
    }

    public function createAccessToken(Request $request)
    {
        $inputs = $request->all();
        $inputs['grant_type']='password';
        $inputs['client_id']=2;
        $inputs['client_secret'] = env('CLIENT_SECRET', 'replace-me');

        $user = null;
        if (isset($inputs['username']) && $inputs['grant_type'] == 'password') {
            $user = $this->userRepository->findOneBy(['email' => $inputs['username']]);
        }

        if (!isset($inputs['scope']) || empty($inputs['scope'])) {
            $inputs['scope'] = "*";
        }

        $tokenRequest = $request->create('/oauth/token', 'post', $inputs);

        return app()->dispatch($tokenRequest);
    }
}