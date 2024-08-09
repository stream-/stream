<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Setting;
use App\Services\Event\EventServices;
use Illuminate\Http\Request;
use \App\Models\Page;
use Carbon\Carbon;

class PageController extends Controller
{
    private $eventServices;

    public function __construct(EventServices $eventServices)
    {
        $this->eventServices = $eventServices;
    }
    /**
     * Summary of listPage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function listPage()
    {
        $type = 1;
		$title = 'Статті';
		\SEO::setTitle($title);
		$pages = Page::front($type,request()->manufacturer)->orderBy('created_at','DESC')->paginate(6);
        return view('information.pages', compact('title','pages','type'));
    }
    /**
     * 
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function listNew()
    {
        $type = 2;
		$title = 'Новини';
		\SEO::setTitle($title);
		$pages = Page::front($type,request()->manufacturer)->orderBy('created_at','DESC')->paginate(14);

        $pages->transform(function ($page) {
            $page->date = Carbon::parse($page->created_at)->format('F d, Y');
            return $page;
        });
        return view('front.news.index', compact('title','pages','type'));
    }
    /**
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function listWebinar()
    {
        $type = 3;
		$title = 'Вебінари';
		\SEO::setTitle($title);
		$pages = Page::front($type,request()->manufacturer)->orderBy('created_at','DESC')->paginate(6);
        return view('information.pages', compact('title','pages','type'));
    }
    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function listVideo(Request $request)
    {
        $type = 4;
        $title = 'Відео';
        \SEO::setTitle($title);
        $pages = Page::front($type,$request->manufacturer)->orderBy('created_at','DESC')->paginate(6);
        return view('information.pages', compact('title','pages','type'));
    }
    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function listLibraryTexture(Request $request)
    {
        $type = 5;
        $title = 'Бібліотека текстур';
        \SEO::setTitle($title);
        $pages = Page::front($type,$request->manufacturer)->orderBy('created_at','DESC')->paginate(6);
        return view('information.pages', compact('title','pages','type'));
    }
    /**
     * 
     * @param mixed $video
     * @return mixed
     */
	public function getVideo($video){
		$embed = \Embed::make($video.'?controls=1')->parseUrl();
		if($embed) {
            $embed->setAttribute(['webkitallowfullscreen','mozallowfullscreen','allowfullscreen']);
            $embed->setAttribute(['allow'=>'autoplay; fullscreen']);
            return $embed->getHtml();
        }
		return false;
	}
    /**
     * 
     * @param mixed $slug
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
	public function showPage($slug) {
		$entry = Page::front()->whereSlug($slug)->firstOrFail();
		\SEO::setTitle($entry->title);
		$entry->video = $this->getVideo($entry->video);

        return view('information.page', compact('entry'));
	}
    /**
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
	public function showAbout(){
		return view('information.about');
	}
    /**
     * 
     * @param mixed $slug
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
	public function showFaq($slug){
        $entry = Faq::whereSlug($slug)->firstOrFail();
        \SEO::setTitle($entry->title);

        return view('information.faq',compact('entry'));

    }
    /**
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function marathonShow(Request $request)
    {
        $title = 'Марафон сироваток';
        \SEO::setTitle($title);
        $compareIDs = [26,27,28,29,30,31,32,33,34];
        $entries = $this->eventServices->getCompareByIDs($compareIDs,['events.ballsRelated']);
        $entries = $entries->map(function($item){
            if($event = $item->events->first()) {
                $item->datetime = $event->datetime;
            }else{
                $item->datetime = null;
            }

            return $item;
        })->sortBy('datetime');
        $type='courses';
        $ind=16;
        return view('information.marathon.show', compact('entries','title','type','ind'));
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function marathonBeautyShow()
    {
        $title = 'Марафон краси';
        return view('information.marathonbeauty.show', compact('title'));
    }
    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function marathonChange(Request $request)
    {
        $event = $this->eventServices->frontById($request->id, ['ballsRelated','compare_many.events.ballsRelatedAuth']);
        $count = $event->compare_many->limit($request->id);
        return [
            'success' => true,
            'text' => view('information.marathon.event_btn',compact('event'))->render(),
            'count' => $count <= 0 ? 0 : $count
        ];
    }
    /**
     * @param mixed $slug
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function showNews($slug) {
        $entry = Page::front()->whereSlug($slug)->firstOrFail();
		\SEO::setTitle($entry->title);
		$entry->video = $this->getVideo($entry->video);
        $entry->date = Carbon::parse($entry->created_at)->format('F d, Y');

        $otherNews = Page::front()->whereType(2)->orderByDesc('id')->limit(3)->get();

        return view('front.news.show', compact('entry', 'otherNews'));
    }
    /**
     * 
     * @return mixed|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function devMode()
    {
        if(!Setting::where('field', 'dev_mode')->where('value', true)->exists()){
            return redirect()->route('home');
        }

        return view('front.devMode');
    }
}
