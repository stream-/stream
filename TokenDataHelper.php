<?php
namespace app\models;

use Yii;

class TokenDataHelper extends \yii\db\ActiveRecord {
    const TIMEFRAME_DAY = 'day';
    const TIMEFRAME_WEEK = 'week';
    const TIMEFRAME_MONTH = 'month';
    const TIMEFRAME_3MONTHS = '3months';
    const TIMEFRAME_YEAR = 'year';
    const TIMEFRAME_ALL = 'all';

    const PRICE_CHANGE_PERIOD_1H = '1h';
    const PRICE_CHANGE_PERIOD_24H = '24h';
    const PRICE_CHANGE_PERIOD_7D = '7d';

    public static function calculatePriceChange($tid, $period = NULL) {
        $computedPrice = [];

        $periodTimeMod = [
            self::PRICE_CHANGE_PERIOD_1H => ['mod' => '-1 hour', 'shift' => 900],
            self::PRICE_CHANGE_PERIOD_24H => ['mod' => '-1 day', 'shift' => 1800],
            self::PRICE_CHANGE_PERIOD_7D => ['mod' => '-1 week', 'shift' => 3600],
        ];

        if ($tokenData = TokenExtData::findOne(['symbol_id' => $tid])) {
            $currentPrice = $tokenData->priceOracle;

            if ($period && in_array($period, array_keys($periodTimeMod))) {
                $pdata = self::fetchPriceFromHistory($tid, $periodTimeMod[$period]['mod'], $periodTimeMod[$period]['shift']);

                if ($pdata) {
                    $computedPrice[$period] = self::computePriceChange($pdata['val'], $currentPrice);
                }
            } else {
                foreach ($periodTimeMod as $ptk => $time) {
                    $pdata = self::fetchPriceFromHistory($tid, $time['mod'], $time['shift']);

                    if ($pdata) {
                        $computedPrice[$ptk] = self::computePriceChange($pdata['val'], $currentPrice);
                    }
                }
            }

        }

        return $computedPrice;
    }

    private static function computePriceChange($oldPrice, $newPrice) {
        $pc = ((($newPrice - $oldPrice) / $oldPrice) * 100);

        return [
            'direction' => $pc < 0 ? 'down' : 'up',
            'val' => number_format($pc, 2),
        ];
    }

    public static function searchPricesByTimeframe($tokenId, $timeframe, $chartType, $lastDate = NULL) {
        $dates = self::formatDatesByPeriod($timeframe);

        if ($lastDate) {
            $dates['begin'] = $lastDate;
        }
        
        $periodId = TokenPricePeriodType::getPeriodId(TokenPricePeriodType::PERIOD_1HOUR);
        $orderField = 'date';
        $prices_data = [];
        switch ($chartType) {
            case 'line':
            case 'baseline':
            case 'area':
                if ($timeframe == self::TIMEFRAME_DAY || $timeframe == self::TIMEFRAME_WEEK) {
                    $prices = (new \yii\db\Query())->select('`symbol_id`, `time` as time, `amount` as value')
                    ->from('symbol_price');
                    //TokenPrice::find()->select('`symbol_id`, `time` as time, `amount` as value');
                    $orderField = 'time';
                    $prices->where(['symbol_id' => $tokenId[0]])
                        ->andWhere(['>', 'time', $dates['begin']]);
                    foreach ($tokenId as $k => $tkn_id) {
                        $price_sql = (new \yii\db\Query())->select('`symbol_id`, `time` as time, `amount` as value')
                        ->from('symbol_price')
                        ->where(['symbol_id' => $tkn_id])
                        ->andWhere(['>', 'time', $dates['begin']]);
                        $prices->union($price_sql);
                        //$price_sql->orderBy(['symbol_id' => SORT_ASC, $orderField => SORT_ASC]);
                        //$prices_data[] = $price_sql->all();
                    }
                    
                } else {
                    $prices = (new \yii\db\Query())->select('`symbol_id`, `date` as time, `close` as value')
                    //TokenPricePeriod::find()->select('`symbol_id`, `date` as time, `close` as value');
                    ->from('symbol_price_period');
                    $prices->where(['symbol_id' => $tokenId[0]])
                        ->andWhere(['period_id' => $periodId])
                        ->andWhere(['>', 'date', $dates['begin']]);
                    
                    foreach ($tokenId as $k => $tkn_id) {
                        $price_sql = (new \yii\db\Query())->select('`symbol_id`, `date` as time, `close` as value')
                        ->from('symbol_price_period')
                        ->where(['symbol_id' => $tkn_id])
                        ->andWhere(['period_id' => $periodId])
                        ->andWhere(['>', 'date', $dates['begin']]);
                        $prices->union($price_sql);
                        
                }
                }

                break;
           
            case 'bar':
            case 'candlestick':
                $prices = (new \yii\db\Query())->select('`symbol_id`, `open` as open, `close` as close, `high` as high, `low` as low, `date` as time')
                    
                    
                ->from('symbol_price_period')
                ->where(['symbol_id' => $tokenId[0]])
                    ->andWhere(['period_id' => $periodId])
                    ->andWhere([($lastDate ? '>=' : '>'), 'date', $dates['begin']]);
                foreach ($tokenId as $k => $tkn_id) {
                    $price_sql = (new \yii\db\Query())->select('`symbol_id`, `open` as open, `close` as close, `high` as high, `low` as low, `date` as time')
                    ->from('symbol_price_period')
                     ->where(['symbol_id' => $tkn_id])
                    ->andWhere(['period_id' => $periodId])
                    ->andWhere([($lastDate ? '>=' : '>'), 'date', $dates['begin']]);
                    
                    $prices->union($price_sql);
                }
                break;
            case 'sparkline':
                // simple cut by hour
                //TokenPricePeriod
                $prices = (new \yii\db\Query())->select('`symbol_id`, `date` as time, `close` as value')
                ->from('symbol_price_period')
                ->where(['symbol_id' => $tokenId[0]])
                ->andWhere(['period_id' => $periodId])
                //andWhere(['period_id' => $periodId])
                ->andWhere(['>', 'date', $dates['begin']]);
                //$prices->createCommand()->getRawSql();
                foreach ($tokenId as $k => $tkn_id) {
                    $price_sql = (new \yii\db\Query())->select('`symbol_id`, `date` as time, `close` as value')
                    ->from('symbol_price_period')
                    ->where(['symbol_id' => $tkn_id])
                    ->andWhere(['period_id' => $periodId])
                    //andWhere(['period_id' => $periodId])
                    ->andWhere(['>', 'date', $dates['begin']]);
                    //$price_sql->orderBy(['symbol_id' => SORT_ASC, $orderField => SORT_ASC]);
                    //$prices_data[] = $price_sql->all();
                    $prices->union($price_sql);
                }
                
                break;
            default:
                return [];
        }

        $prices->orderBy(['symbol_id' => SORT_ASC, $orderField => SORT_ASC]);
        
        
        return $prices->all() ?: [];
    }

    public static function searchDexPricesByTimeframe($tokenId, $timeframe, $chartType, $lastDate = NULL) {
        $dates = self::formatDatesByPeriod($timeframe);

        if ($lastDate) {
            $dates['begin'] = $lastDate;
        }

        $prices = (new yii\db\Query())->select('`symbol_id`, `time` as time, `amount` as value')
            //TokenPriceDex::find()->select('`symbol_id`, `time` as time, `amount` as value')
            ->from('symbol_price_dex')
            ->where(['symbol_id' => $tokenId[0]])
            ->andWhere(['>', 'time', $dates['begin']]);
            //->orderBy(['symbol_id' => SORT_ASC, 'time' => SORT_ASC]);
        
        foreach ($tokenId as $k => $tkn_id) {
            $price_sql = (new \yii\db\Query())->select('`symbol_id`, `time` as time, `amount` as value')
            ->from('symbol_price_dex')
            ->where(['symbol_id' => $tkn_id])
            ->andWhere(['>', 'time', $dates['begin']]);
              $prices->union($price_sql);  
        }
        $prices->orderBy(['symbol_id' => SORT_ASC, 'time' => SORT_ASC]);
        return $prices->all() ?: [];
    }


    /**
     * function returns token oracle and dex prices
     * tokens search by token id
     *
     * @param $tid number | array
     * @return array
     */
    public static function findPriceByTokenId($tid) {
        $tdata = [];

        if (!is_array($tid)) {
            $tid = [$tid];
        }

        $tokensData = (new yii\db\Query())->select('*')->from('symbol_ext_data')->where(['symbol_id' => $tid[0]]);//->all();
        foreach ($tid as $id => $tok_id) {
            $token_sql = (new \yii\db\Query())->select('*')->from('symbol_ext_data')->where(['symbol_id' => $tok_id]);
            $tokensData->union($token_sql);  
        }
        //TokenExtData::find()->where(['symbol_id' => $tid])->all();
        $tokensData = $tokensData->all();
        foreach ($tokensData as $data) {
            $tdata[$data->symbol_id] = ['oracle' => $data->priceOracle, 'dex' => $data->priceDex];
        }

        return $tdata;
    }

    /**
     * function returns token oracle and dex prices
     * tokens search by token id
     *
     * @param $code string | array
     * @return array
     */
    public static function findPriceByTokenCode($tcode) {
        $tdata = [];

        if (!is_array($tcode)) {
            $tcode = [$tcode];
        }

        $tokens = Token::find()->where(['code' => $tcode])->all();

        foreach ($tokens as $token) {
            $ext = $token->extData;
            if ($ext) {
                $tdata[$token->code] = ['oracle' => $ext->priceOracle, 'dex' => $ext->priceDex];
            }
        }

        return $tdata;
    }

    public static function codeLTrimmed($code) {
        return substr($code, 1);
    }

    private static function formatDatesByPeriod($timeframe, $asTimestamp = TRUE) {
        $begin = new \DateTime();
        $end = new \DateTime();
        $end->setTimestamp($end->getTimestamp() - 1);

        switch ($timeframe) {
            case self::TIMEFRAME_WEEK:
                $begin->sub(new \DateInterval('P7D'));
                break;

            case self::TIMEFRAME_MONTH:
                $begin->sub(new \DateInterval('P1M'));
                break;

            case self::TIMEFRAME_3MONTHS:
                $begin->sub(new \DateInterval('P3M'));
                break;

            case self::TIMEFRAME_YEAR:
                $begin->sub(new \DateInterval('P1Y'));
                break;

            case self::TIMEFRAME_ALL:
                $begin->sub(new \DateInterval('P3Y'));
                break;

            default: //  self::TIMEFRAME_DAY
                $begin->sub(new \DateInterval('PT24H'));
                break;
        }

        return [
            'begin' => $asTimestamp ? $begin->getTimestamp() : $begin,
            'end' => $asTimestamp ? $end->getTimestamp() : $end
        ];
    }

    private static function fetchPriceFromHistory($tid, $timeModif, $timeShift) {
        $time = (new \DateTime())->modify($timeModif);
        $price = self::fetchOneHistoryPrice($tid, $time->getTimestamp() - $timeShift, $time->getTimestamp() + $timeShift);

        if ($price) {

            $data['time'] = $price->time;
            $data['val'] = $price->amount;

            return $data;
        }

        return NULL;
    }

    private static function fetchOneHistoryPrice($tid, $minTime, $maxTime) {
        return TokenPrice::find()->where(['symbol_id' => $tid])->andWhere(['between', 'time', $minTime, $maxTime])->one();
    }
}
