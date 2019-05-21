<?php

namespace App\Api\Controllers\Wx;

use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Services\LocationService;
use App\Api\Controllers\Controller;
use App\Api\Transformers\LocationTransformer;
use App\Repositories\Location\LocationRepository;

/**
 * Class LocationController
 * @package App\Api\Controllers\Wx
 */
class LocationController extends Controller
{

    /**
     * @var LocationRepository
     */
    protected $location;

    /**
     * @var LocationTransformer
     */
    protected $locationTransformer;

    /**
     * Location constructor.
     * @param LocationRepository $location
     * @param LocationTransformer $locationTransformer
     */
    public function __construct(LocationService $locationService, AuthService $authService)
    {
        $this->locationService = $locationService;
        $this->authService = $authService;
    }

    public function Index()
    {
        $region = $this->locationService->index();
        return $region;
    }

    public function Info(Request $request)
    {
        $this->validate($request, [
            'region_id' => 'required|int',
            'region_type' => 'required|int',
        ]);
        $region = $this->locationService->info($request->get('region_id'), $request->get('region_type'));
        return $region;
    }

    public function getcity()
    {
        $region = $this->locationService->getcity();
        return $region;
    }

    public function setcity()
    {
        $region = $this->locationService->setcity();
        return $region;
    }

    public function specific(Request $request)
    {
        $this->validate($request, [
            'address' => 'required|string',
        ]);
        $region = $this->locationService->specific($request->get('address'));
        return $region;
    }
}
