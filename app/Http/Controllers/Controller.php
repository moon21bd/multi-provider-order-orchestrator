<?php //app/Http/Controllers/Controller.php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use League\Fractal\Manager;

class Controller extends BaseController
{
    use ResponseTrait;

    /**
     * Constructor
     *
     * @param Manager|null $fractal
     */
    public function __construct(Manager $fractal = null)
    {
        $fractal = $fractal === null ? new Manager() : $fractal;
        $this->setFractal($fractal);
    }

    /**
     * Validate HTTP request against the rules
     *
     * @param Request $request
     * @param array $rules
     * @return bool|array
     */
    protected function validateRequest(Request $request, array $rules)
    {
        // Perform Validation
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $errorMessages = $validator->errors()->messages();

            // crete error message by using key and value
            foreach ($errorMessages as $key => $value) {
                $errorMessages[$key] = $value[0];
            }

            return $errorMessages;
        }

        return true;
    }

    protected function formatError($code, $message){
        return new JsonResponse([
            'status_code'=>$code,
            'message'=>$message
        ],$code);
    }

    protected function format($code, $message, $data = null){
        if($data == null){
            return $this->formatError($code,$message);
        }
        return new JsonResponse([
            'status_code'=>$code,
            'message'=>$message,
            'data' => $data
        ]);
    }

    protected function formatRes($code, $message, $data = null){
        if($data == null){
            return $this->formatError($code,$message);
        }
        return new JsonResponse([
            'status'=>$code,
            'data' => $data
        ]);
    }

    protected function formatEr($code, $message){
        return new JsonResponse([
            'status'=>$code,
            'error'=>$message
        ],$code);
    }
}
