<?php
namespace Mobile\Controller;

class MorefindController extends MobileController
{
	public function index()
	{
		
		$CoinList = M('Coin')->where(array('status' => 1))->select();
		$UserCoin = M('UserCoin')->where(array('userid' => userid()))->find();
		$Market = M('Market')->where(array('status' => 1))->select();

		foreach ($Market as $k => $v) {
			$Market[$v['name']] = $v;
		}

		$cny['zj'] = 0;

		foreach ($CoinList as $k => $v) {
			if ($v['name'] == 'cny') {
				$cny['ky'] = round($UserCoin[$v['name']], 2) * 1;
				$cny['dj'] = round($UserCoin[$v['name'] . 'd'], 2) * 1;
				$cny['zj'] = $cny['zj'] + $cny['ky'] + $cny['dj'];
			} else {
				if ($Market[$v['name'].'_'.Anchor_CNY]['new_price']) {
					$jia = $Market[$v['name'].'_'.Anchor_CNY]['new_price'];
				} else {
					$jia = 1;
				}

				$coinList[$v['name']] = array(
					'id' => $v['id'],
					'name' => $v['name'], 
					'img' => $v['img'], 
					'title' => $v['title'], 
					'xnb' => round($UserCoin[$v['name']], 6) * 1, 
					'xnbd' => round($UserCoin[$v['name'] . 'd'], 6) * 1, 
					'xnbz' => round($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd'], 6), 
					'jia' => $jia * 1, 
					'zhehe' => round(($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia, 2)
				);
				
				$coinList[$v['name']]['zhehe'] = sprintf("%.4f", $coinList[$v['name']]['zhehe']);
				$cny['zj'] = round($cny['zj'] + (($UserCoin[$v['name']] + $UserCoin[$v['name'] . 'd']) * $jia), 2) * 1;
				$coinList[$v['name']]['xnb'] = sprintf("%.4f", $coinList[$v['name']]['xnb']);
				$coinList[$v['name']]['xnbd'] = sprintf("%.4f", $coinList[$v['name']]['xnbd']);
				$coinList[$v['name']]['xnbz'] = sprintf("%.4f", $coinList[$v['name']]['xnbz']);
				$coinList[$v['name']]['zhehe'] = sprintf("%.2f", $coinList[$v['name']]['zhehe']);
				//$coinList[$v['name']]['zhehe'] = number_format($coinList[$v['name']]['zhehe'],2);//千分位显示
				
				$coinList[$v['name']]['token_type'] = $v['token_type'];
			}
		}

		$cny['dj'] = sprintf("%.2f", $cny['dj']);
		$cny['ky'] = sprintf("%.2f", $cny['ky']);
		$cny['zj'] = sprintf("%.2f", $cny['zj']);
		$cny['dj'] = number_format($cny['dj'],2);//千分位显示
		$cny['ky'] = number_format($cny['ky'],2);//千分位显示
		//$cny['zj'] = number_format($cny['zj'],2);//千分位显示

		$this->assign('cny', $cny);
		$this->display();
	}
}