<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\TestUser;
use App\Services\Rating\RatignService;
use Illuminate\Http\Request;

class RatingController extends Controller{

    public $test_user;
    private $ratignService;

    public function __construct(RatignService $ratignService)
    {
        $this->test_user = TestUser::pluck('user_id');
        $this->ratignService = $ratignService;
    }
    /**
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function rating_global_get(){
        $field = request()->get('field');
        $sort = request()->get('sort');
        if (empty($sort)) {
            $sort = 'asc';
        }

        if(!empty($field)){
            $entry = Rating::with(['user.nominate','user.championsInfo'])
                ->filterGlobal()
                ->whereNotIn('user_id',$this->test_user)
                ->when($field === 'rating', function($query) use ($sort) { $query->orderBy('rating', $sort); })
                ->when($field === 'balls', function($query) use ($sort) { $query->orderBy('balls', $sort); })
                ->orderBy($field, $sort)
                ->limit(50)
                ->paginate(50);
        }else{
            $entry = Rating::with(['user.nominate','user.place.city','user.place.network','user.championsInfo'])->filterGlobal()->whereNotIn('user_id',$this->test_user)->orderby('rating','ASC')->limit(50)->paginate(50);
        }

        // $entry = Rating::with(['user.nominate','user.championsInfo'])->paginate(50);

        if(!empty($field)){
            $sort = 'asc';
            if(request()->get('sort') == 'asc'){
                $sort = 'desc';
            }
        }

        return  view('rating.get',compact('entry','sort'));
    }
    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function rating_filter_global(Request $request){
        $entry = Rating::whereNotIn('user_id',$this->test_user)->whereHas('user' , function($query) use ($request){
            $query->whereHas('places' , function($query) use ($request){
                $query->when($request->place_id, function($query) use ($request){
                    $query->whereId($request->place_id);
                });
                $query->when($request->n_id, function($query) use ($request){
                    $query->whereHas('network' , function($query) use ($request){
                        $query->whereId($request->n_id);
                    });
                });
                $query->when($request->city_id, function($query) use ($request){
                    $query->whereHas('city' , function($query) use ($request){
                        $query->whereId($request->city_id);
                    });
                });


            });
        })->orderby('rating','ASC')->limit(50)->paginate(50);
        $balls_sort = $tranings_sort = 'desc';

        return  view('rating.get_rows',compact('entry','balls_sort','tranings_sort'));
    }
    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function rating_bloger_index(Request $request)
    {
        $entry = $this->ratignService->getFilterBlogerPharmacies($request);

        return view('rating.bloger.get',compact('entry'));
    }
    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function rating_bloger_ajax(Request $request){
        $entry = $this->ratignService->getFilterBlogerPharmacies($request);

        return view('rating.bloger.get_rows',compact('entry'));
    }
    /**
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function rating_traning_get(){
        $balls_sort = $tranings_sort = 'desc';

        if(request()->has('field')){
            $entry = Rating::filterTraning()->whereNotIn('user_id',$this->test_user)->orderBy(request()->get('field'),request()->get('sort'))->limit(50)->get();
        }else{
            $entry = Rating::filterTraning()->whereNotIn('user_id',$this->test_user)->orderby('rating_traning','ASC')->limit(50)->get();
        }
        if(request()->has('field')){
            $sort = 'asc';
            if(request()->get('sort') == 'asc'){
                $sort = 'desc';
            }
            if(request()->get('field') == 'balls_traning'){
                $balls_sort = $sort;
            }
            if(request()->get('field') == 'tranings'){
                $tranings_sort = $sort;
            }

        }
        return  view('rating.get_traning',compact('entry','balls_sort','tranings_sort'));
    }
    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function rating_filter_traning(Request $request){
        $entry = Rating::filterTraning()->whereNotIn('user_id',$this->test_user)->whereHas('user' , function($query) use ($request){
            $query->whereHas('places' , function($query) use ($request){
                $query->when($request->place_id, function($query) use ($request){
                    $query->whereId($request->place_id);
                });
                $query->when($request->n_id, function($query) use ($request){
                    $query->whereHas('network' , function($query) use ($request){
                        $query->whereId($request->n_id);
                    });
                });
                $query->when($request->city_id, function($query) use ($request){
                    $query->whereHas('city' , function($query) use ($request){
                        $query->whereId($request->city_id);
                    });
                });


            });
        })->orderby('rating_traning','ASC')->limit(50)->get();
        $balls_sort = $tranings_sort = 'desc';

        return  view('rating.get_rows_traning',compact('entry','balls_sort','tranings_sort'));
    }
}
