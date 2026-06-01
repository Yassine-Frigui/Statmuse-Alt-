<?php

namespace App\Enums;

enum IntentType: string
{
    case RankingQuery = 'ranking_query';
    case PlayerInfo = 'player_info';
    case TeamInfo = 'team_info';
    case ChampionshipQuery = 'championship_query';
    case HistoricalEvent = 'historical_event';
    case ComparisonQuery = 'comparison_query';
    case SeasonStats = 'season_stats';
    case HeadToHead = 'head_to_head';
    case AwardQuery = 'award_query';
    case RuleExplanation = 'rule_explanation';
}
