<?php
/**
 * Bayesian Rate Helper
 *
 * Implements Bayesian estimation for fair rating where:
 * - Low vote counts are dampened by pseudo-votes
 * - Multiple tie-breakers ensure fair ranking
 *
 * Formula: WR = (v / (v + m)) * R + (m / (v + m)) * C
 * Where:
 *   WR = Weighted Rating
 *   v  = Number of ratings for this beer
 *   m  = Pseudo vote count
 *   R  = Average rating for this beer
 *   C  = Average rating across all beers in the class
 */

require_once '_config.php';

class BayesianRateHelper {

    // Placing assigned to beers with no valid score (unranked)
    const LAST_PLACING = 99999;

    private $pseudoVoteCount;
    private $minimumVotesThreshold;
    private $minimumBrewsWithEnoughVotes;

    public function __construct($competitionId = null) {
        $this->pseudoVoteCount = getCompetitionSetting($competitionId, 'BAYESIAN_PSEUDO_VOTE_COUNT', 45);
        $this->minimumVotesThreshold = getCompetitionSetting($competitionId, 'BAYESIAN_MIN_VOTES_THRESHOLD', 20);
        $this->minimumBrewsWithEnoughVotes = getCompetitionSetting($competitionId, 'BAYESIAN_MIN_BREWS_WITH_ENOUGH_VOTES', 1);
    }

    /**
     * Calculate Bayesian weighted score for a beer
     *
     * @param array $brewVotes - All ratings for this specific beer
     * @param array $allClassVotes - All ratings in the competition class
     * @return float - Bayesian weighted score
     */
    public function calculateWeightedScore($brewVotes, $allClassVotes) {
        if (empty($allClassVotes) || empty($brewVotes)) {
            return 0.0;
        }

        $brewVotesCount = count($brewVotes);
        if ($brewVotesCount == 0) {
            return 0.0;
        }

        // Calculate average rating for this beer
        $beerAverageRating = array_sum(array_column($brewVotes, 'ratingScore')) / $brewVotesCount;

        // Calculate average rating across all beers in the class
        $classAverageRating = array_sum(array_column($allClassVotes, 'ratingScore')) / count($allClassVotes);

        // Bayesian estimation formula
        $weightedRating = ($brewVotesCount / ($brewVotesCount + $this->pseudoVoteCount)) * $beerAverageRating
                        + ($this->pseudoVoteCount / ($brewVotesCount + $this->pseudoVoteCount)) * $classAverageRating;

        return $weightedRating;
    }

    /**
     * Calculate Bayesian weighted score using a provided mean
     * Used for Best In Show calculation where global mean replaces class mean
     *
     * @param array $brewVotes - All votes for this beer
     * @param float $globalMean - Pre-calculated mean to use (for example global mean across all classes)
     * @return float - Bayesian weighted score
     */
    public function calculateWeightedScoreWithMean($brewVotes, $globalMean) {
        if (empty($brewVotes) || $globalMean <= 0) {
            return 0.0;
        }

        $brewVotesCount = count($brewVotes);
        if ($brewVotesCount == 0) {
            return 0.0;
        }

        // Calculate average rating for this beer
        $beerAverageRating = array_sum(array_column($brewVotes, 'ratingScore')) / $brewVotesCount;

        // Bayesian estimation formula using provided mean
        $weightedRating = ($brewVotesCount / ($brewVotesCount + $this->pseudoVoteCount)) * $beerAverageRating
                        + ($this->pseudoVoteCount / ($brewVotesCount + $this->pseudoVoteCount)) * $globalMean;

        return $weightedRating;
    }

    /**
     * Calculate median score for tie-breaker
     *
     * @param array $brewVotes - All votes for this beer
     * @return float - Median score
     */
    public function calculateMedianScore($brewVotes) {
        if (empty($brewVotes)) {
            return 0.0;
        }

        $scores = array_column($brewVotes, 'ratingScore');
        sort($scores);
        $count = count($scores);

        if ($count % 2 == 0) {
            return ($scores[$count / 2 - 1] + $scores[$count / 2]) / 2;
        }

        return $scores[(int)floor($count / 2)];
    }

    /**
     * Calculate mean score
     *
     * @param array $brewVotes - All votes for this beer
     * @return float - Mean score
     */
    public function calculateMeanScore($brewVotes) {
        if (empty($brewVotes)) {
            return 0.0;
        }

        return array_sum(array_column($brewVotes, 'ratingScore')) / count($brewVotes);
    }

    /**
     * Calculate standard deviation for tie-breaker
     * Lower standard deviation = more consistent ratings = better
     *
     * @param array $brewVotes - All votes for this beer
     * @return float - Standard deviation
     */
    public function calculateStandardDeviation($brewVotes) {
        if (empty($brewVotes) || count($brewVotes) < 2) {
            return 0.0;
        }

        $mean = $this->calculateMeanScore($brewVotes);
        $sumOfSquares = 0.0;

        foreach ($brewVotes as $vote) {
            $sumOfSquares += pow($vote['ratingScore'] - $mean, 2);
        }

        if ($sumOfSquares == 0) {
            return 0.0;
        }

        $variance = $sumOfSquares / (count($brewVotes) - 1);
        return sqrt($variance);
    }

    /**
     * Calculate results for a single competition class
     *
     * @param array $beers - Array of beers in this class
     * @param array $allVotes - All votes for this class grouped by beerEntryId
     * @return array - Sorted array of beers with Bayesian scores
     */
    public function calculateClassResults($beers, $allVotes) {
        $results = [];
        $allClassVotes = [];

        // Flatten all votes for class average calculation
        foreach ($allVotes as $beerVotes) {
            $allClassVotes = array_merge($allClassVotes, $beerVotes);
        }

        foreach ($beers as $beer) {
            $beerEntryId = $beer['entry_code'];
            $brewVotes = isset($allVotes[$beerEntryId]) ? $allVotes[$beerEntryId] : [];

            $result = [
                'beerEntryId' => $beerEntryId,
                'beerName' => isset($beer['name']) ? $beer['name'] : '',
                'brewer' => isset($beer['brewer']) ? $beer['brewer'] : '',
                'styleName' => isset($beer['styleName']) ? $beer['styleName'] : '',
                'styleId' => isset($beer['styleId']) ? $beer['styleId'] : '',
                'categoryId' => isset($beer['class']) ? $beer['class'] : null,
                'bayesianScore' => 0.0,
                'voteCount' => count($brewVotes),           // Tie-breaker 1
                'medianScore' => 0.0,                       // Tie-breaker 2
                'standardDeviation' => 0.0,                 // Tie-breaker 3 (lower is better)
                'meanScore' => 0.0,
                'placing' => self::LAST_PLACING
            ];

            if (!empty($brewVotes)) {
                $result['bayesianScore'] = $this->calculateWeightedScore($brewVotes, $allClassVotes);
                $result['medianScore'] = $this->calculateMedianScore($brewVotes);
                $result['standardDeviation'] = $this->calculateStandardDeviation($brewVotes);
                $result['meanScore'] = $this->calculateMeanScore($brewVotes);
            }

            $results[] = $result;
        }

        // Check if threshold filter should be applied
        $brewsWithEnoughVotes = count(array_filter($results, function($r) {
            return $r['voteCount'] >= $this->minimumVotesThreshold;
        }));

        if ($brewsWithEnoughVotes >= $this->minimumBrewsWithEnoughVotes) {
            // Reset scores for beers with insufficient votes
            foreach ($results as &$result) {
                if ($result['voteCount'] < $this->minimumVotesThreshold && $result['voteCount'] > 0) {
                    $result['bayesianScore'] = 0.0;
                    $result['voteCount'] = 0;
                    $result['medianScore'] = 0.0;
                    $result['standardDeviation'] = 0.0;
                    $result['meanScore'] = 0.0;
                    $result['filteredOut'] = true;
                }
            }
            unset($result);
        }

        // Sort by Bayesian score, then tie-breakers
        //Mj: compability with older PHP versions without spaceship operator
        usort($results, function($a, $b) {

            // Primary: Bayesian score (descending)
            if ($a['bayesianScore'] != $b['bayesianScore']) {
                if ($b['bayesianScore'] > $a['bayesianScore']) return 1;
                if ($b['bayesianScore'] < $a['bayesianScore']) return -1;
            }

            // Tie-breaker 1: Vote count (descending)
            if ($a['voteCount'] != $b['voteCount']) {
                if ($b['voteCount'] > $a['voteCount']) return 1;
                if ($b['voteCount'] < $a['voteCount']) return -1;
            }

            // Tie-breaker 2: Median score (descending)
            if ($a['medianScore'] != $b['medianScore']) {
                if ($b['medianScore'] > $a['medianScore']) return 1;
                if ($b['medianScore'] < $a['medianScore']) return -1;
            }

            // Tie-breaker 3: Standard deviation (ascending - lower is better)
            if ($a['standardDeviation'] != $b['standardDeviation']) {
                if ($a['standardDeviation'] > $b['standardDeviation']) return 1;
                if ($a['standardDeviation'] < $b['standardDeviation']) return -1;
            }

            return 0;
        });

        // Assign placings
        $currentPlacing = 1;
        foreach ($results as &$result) {
            $result['placing'] = ($result['bayesianScore'] == 0)
                ? self::LAST_PLACING
                : $currentPlacing++;
        }
        unset($result);

        return $results;
    }

    /**
     * Check if a class name indicates it's a label/etikett class
     * Label classes are excluded from Best In Show calculations
     *
     * @param string $className - The class/category name
     * @return bool - True if this is a label class
     */
    public function isLabelClass($className) {
        if (empty($className)) {
            return false;
        }
        $lowerName = mb_strtolower($className, 'UTF-8');
        return strpos($lowerName, 'etikett') !== false || strpos($lowerName, 'label') !== false;
    }

    /**
     * Calculate Best In Show across all classes
     *
     * Algorithm:
     * 1. Calculate mean score across ALL votes from ALL classes (excluding label classes)
     * 2. Get #1 from each class with their Bayesian scores (excluding label classes)
     * 3. Compare using tie-breakers
     *
     * Note: Classes with "etikett" or "label" in their name are excluded from
     * Best In Show competition and their votes don't contribute to the global mean.
     *
     * @param array $classResults - Array of class results from calculateClassResults()
     * @param array $allVotesAllClasses - All votes across all classes
     * @param array $categories - Category info array
     * @return array - Best In Show result with winner and runners-up
     */
    public function calculateBestInShow($classResults, $allVotesAllClasses, $categories = []) {
        // Build a map of classId => className for label detection
        $classNameMap = [];
        foreach ($categories as $cat) {
            $classNameMap[$cat['id']] = isset($cat['name']) ? $cat['name'] : '';
        }
        $candidates = [];

        // Get #1 from each class (excluding label classes)
        foreach ($classResults as $classId => $results) {
            // Skip label classes - they can't compete for Best In Show
            $className = isset($classNameMap[$classId]) ? $classNameMap[$classId] : '';
            if ($this->isLabelClass($className)) {
                continue;
            }

            if (!empty($results) && $results[0]['placing'] == 1) {
                $winner = $results[0];

                // Only include winners that have passed the vote count threshold
                if ($winner['voteCount'] < $this->minimumVotesThreshold) {
                    continue;
                }

                $winner['classId'] = $classId;
                // Add category name if available
                foreach ($categories as $cat) {
                    if ($cat['id'] == $classId) {
                        $winner['className'] = $cat['name'];
                        break;
                    }
                }
                $candidates[] = $winner;
            }
        }

        if (empty($candidates)) {
            return ['winner' => null, 'runnersUp' => [], 'globalMean' => 0, 'totalVotes' => 0];
        }

        // Calculate global mean for normalization reference (excluding label classes)
        $allVotes = [];
        foreach ($allVotesAllClasses as $classId => $classVotes) {
            // Skip label classes - their votes don't contribute to global mean
            $className = isset($classNameMap[$classId]) ? $classNameMap[$classId] : '';
            if ($this->isLabelClass($className)) {
                continue;
            }

            foreach ($classVotes as $beerVotes) {
                $allVotes = array_merge($allVotes, $beerVotes);
            }
        }
        $globalMean = !empty($allVotes)
            ? array_sum(array_column($allVotes, 'ratingScore')) / count($allVotes)
            : 0;

        // Recalculate Bayesian score for each candidate using global mean
        // This ensures fair comparison across classes with different class averages
        foreach ($candidates as &$candidate) {
            $classId = $candidate['classId'];
            $beerEntryId = $candidate['beerEntryId'];

            // Get this beer's votes from the votes array
            $brewVotes = isset($allVotesAllClasses[$classId][$beerEntryId])
                ? $allVotesAllClasses[$classId][$beerEntryId]
                : [];

            // Calculate BIS score using global mean instead of class mean
            $candidate['bisScore'] = $this->calculateWeightedScoreWithMean($brewVotes, $globalMean);
        }
        unset($candidate);

        // Sort candidates by BIS score (recalculated with global mean) and tie-breakers
        //Mj: compability with older PHP versions without spaceship operator
        usort($candidates, function($a, $b) {

            // Primary: BIS score (descending)
            if ($a['bisScore'] != $b['bisScore']) {
                if ($b['bisScore'] > $a['bisScore']) return 1;
                if ($b['bisScore'] < $a['bisScore']) return -1;
            }

            // Tie-breaker 1: Vote count (descending)
            if ($a['voteCount'] != $b['voteCount']) {
                if ($b['voteCount'] > $a['voteCount']) return 1;
                if ($b['voteCount'] < $a['voteCount']) return -1;
            }

            // Tie-breaker 2: Median score (descending)
            if ($a['medianScore'] != $b['medianScore']) {
                if ($b['medianScore'] > $a['medianScore']) return 1;
                if ($b['medianScore'] < $a['medianScore']) return -1;
            }

            // Tie-breaker 3: Standard deviation (ascending)
            if ($a['standardDeviation'] != $b['standardDeviation']) {
                if ($a['standardDeviation'] > $b['standardDeviation']) return 1;
                if ($a['standardDeviation'] < $b['standardDeviation']) return -1;
            }

            return 0;
        });

        return [
            'winner' => $candidates[0],
            'runnersUp' => array_slice($candidates, 1),
            'globalMean' => $globalMean,
            'totalVotes' => count($allVotes)
        ];
    }

    /**
     * Get the current settings used for Bayesian calculations
     *
     * @return array - Current setting values
     */
    public function getSettings() {
        return [
            'pseudoVoteCount' => $this->pseudoVoteCount,
            'minimumVotesThreshold' => $this->minimumVotesThreshold,
            'minimumBrewsWithEnoughVotes' => $this->minimumBrewsWithEnoughVotes
        ];
    }
}
