<?php

namespace App\Http\Controllers;

use App\Models\CampaignLog;
use App\Models\Campaign;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // 1. Top Bar Stats
        $totalOutreach = CampaignLog::where('user_id', $userId)->count();

        $activeCampaigns = Campaign::where('user_id', $userId)
            ->where('campaign_status', 'active')
            ->count();

        $successCount = CampaignLog::where('user_id', $userId)
            ->whereIn('status', ['sent'])
            ->count();

        $successRate = $totalOutreach > 0 ? round(($successCount / $totalOutreach) * 100, 1) : 0;

        // 2. Daily Volume Chart (Last 7 Days)
        $dailyLogs = CampaignLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(6))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        $chartLabels = [];
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('M d');
            $chartData[] = $dailyLogs[$date] ?? 0;
        }

        // 3. Campaign Distribution
        $distribution = Campaign::where('user_id', $userId)
            ->selectRaw('campaign_type, COUNT(*) as count')
            ->groupBy('campaign_type')
            ->pluck('count', 'campaign_type');

        // 4. Top 5 Popular Templates
        // We join logs with campaigns, then join with templates based on the channel
        $popularTemplates = DB::table('campaign_logs')
            ->join('campaigns', 'campaign_logs.campaign_id', '=', 'campaigns.id')
            ->join('templates', function ($join) {
                $join->on('templates.id', '=', DB::raw('CASE 
                    WHEN campaign_logs.channel = "whatsapp" THEN campaigns.message_template_id 
                    ELSE campaigns.email_template_id 
                END'));
            })
            ->where('campaign_logs.user_id', $userId)
            ->select('templates.name', DB::raw('COUNT(campaign_logs.id) as total'))
            ->groupBy('templates.id', 'templates.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('welcome', compact(
            'totalOutreach',
            'activeCampaigns',
            'successRate',
            'chartLabels',
            'chartData',
            'distribution',
            'popularTemplates'
        ));
    }
}
