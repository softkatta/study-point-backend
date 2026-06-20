<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchPublicResource;
use App\Http\Resources\FaqResource;
use App\Http\Resources\TestimonialResource;
use App\Http\Resources\FacilityResource;
use App\Http\Resources\PlanPublicResource;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\Testimonial;
use App\Models\Plan;
use App\Services\HomePageStatsService;
use App\Services\NotificationChannelService;
use App\Services\PaymentGatewayService;
use App\Services\PublicTopBarService;
use App\Models\Setting;
use App\Support\ApiResponse;
use App\Support\HomepageHeroDefaults;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function __construct(
        private PaymentGatewayService $gateway,
        private NotificationChannelService $channels,
        private HomePageStatsService $homePageStats,
        private PublicTopBarService $publicTopBar,
    ) {}

    public function branches(): JsonResponse
    {
        $branches = Branch::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'city']);

        return ApiResponse::success($branches);
    }

    public function publicBranches(): JsonResponse
    {
        $branches = Branch::query()
            ->where('status', 'active')
            ->withCount('students')
            ->with('managers')
            ->orderByDesc('is_head_office')
            ->orderBy('city')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(BranchPublicResource::collection($branches));
    }

    public function facilities(): JsonResponse
    {
        $facilities = Facility::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return ApiResponse::success(FacilityResource::collection($facilities));
    }

    public function faqs(): JsonResponse
    {
        $faqs = Faq::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(FaqResource::collection($faqs));
    }

    public function testimonials(): JsonResponse
    {
        $rows = Testimonial::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(TestimonialResource::collection($rows));
    }

    public function homepageStats(): JsonResponse
    {
        return ApiResponse::success($this->homePageStats->build());
    }

    public function homepageHero(): JsonResponse
    {
        return ApiResponse::success(HomepageHeroDefaults::merge(Setting::getSection('homepage_hero')));
    }

    public function topBar(): JsonResponse
    {
        return ApiResponse::success($this->publicTopBar->build());
    }

    public function plans(): JsonResponse
    {
        $plans = Plan::query()
            ->where('status', 'active')
            ->orderBy('price')
            ->get();

        return ApiResponse::success(PlanPublicResource::collection($plans));
    }

    public function paymentCheckout(): JsonResponse
    {
        return ApiResponse::success($this->gateway->checkoutConfig());
    }

    public function notificationChannels(): JsonResponse
    {
        return ApiResponse::success($this->channels->publicAvailability());
    }
}
