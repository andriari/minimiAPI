<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class SitemapController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

	public function getSitemapUrl(Request $request){
		$data = $request->all();
		try{
			$return = $this->sitemapUrl_exe('sitemap');
			return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'sitemap_product_failed']);
	  	}
	}

	public function getURL(Request $request){
		$data = $request->all();
		try{
			$return = $this->sitemapUrl_exe();
			$product_urls = $return['product'];
			$article_urls = $return['article'];
			$video_urls = $return['video'];
			$user_urls = $return['user'];
			$category_urls = $return['category'];

			$url['main'] = $this->mainUrl_exe();
			$url['product'] = $product_urls;
			$url['article'] = $article_urls;
			$url['video'] = $video_urls;
			$url['user'] = $user_urls;
			$url['category'] = $category_urls;

			return response()->json(['code'=>200,'message'=>'success','data'=>$url]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'get_url_failed']);
	  	}
	}

	public function mainUrl_exe(){
		$main_url = substr(env('FRONTEND_URL'), 0, -1);

		$url = array();
		array_push($url,$main_url);
		array_push($url,env('FRONTEND_URL').'review');
		array_push($url,env('FRONTEND_URL').'video');
		array_push($url,env('FRONTEND_URL').'article');
		array_push($url,env('FRONTEND_URL').'login');
		array_push($url,env('FRONTEND_URL').'signup');
		array_push($url,env('FRONTEND_URL').'add-video');
		array_push($url,env('FRONTEND_URL').'add-review');
		array_push($url,env('FRONTEND_URL').'search');
		array_push($url,env('FRONTEND_URL').'edit-profile');
		array_push($url,env('FRONTEND_URL').'generate-sitemap');
		array_push($url,env('FRONTEND_URL').'terms-and-conditions');
		array_push($url,env('FRONTEND_URL').'privacy-policy');
		array_push($url,env('FRONTEND_URL').'points');
		array_push($url,env('FRONTEND_URL').'points/redeem');
		array_push($url,env('FRONTEND_URL').'add-product');
		array_push($url,env('FRONTEND_URL').'success');

		return $url;
	}

	public function sitemapUrl_exe($mode=""){
		//product
		$query = DB::table('minimi_product')
			->select('product_uri','minimi_product.updated_at')
			->join('data_category','data_category.category_id','=','minimi_product.category_id')
			->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
			->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
			->where('minimi_product.status',1)
			->where([
				'data_category.status' => 1,
				'data_category_sub.status' => 1
			])
		->get();
		$product_url = array();
		if(count($query)>0){
			if($mode=="sitemap"){
				$sitemap = array();
				foreach ($query as $row) {
					$sitemap['loc'] = env('FRONTEND_URL').'product/'.$row->product_uri;
					$sitemap['last_update'] = date('c',strtotime($row->updated_at));
					array_push($product_url,$sitemap);
				}
			}else{
				foreach ($query as $row) {
					$loc = env('FRONTEND_URL').'product/'.$row->product_uri;
					array_push($product_url,$loc);
				}	
			}
		}	

		//article
		$query = DB::table('minimi_content_post')
			->select('content_uri','minimi_content_post.updated_at')
			->leftJoin('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
			->leftJoin('minimi_product','minimi_content_post.product_id','=','minimi_product.product_id')
			->leftJoin('data_category','data_category.category_id','=','minimi_product.category_id')
			->leftJoin('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
			->leftJoin('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
			->where([
				'content_curated'=>1,
				'minimi_content_post.status'=>1,
				'content_type'=>3
			])
			->orderBy('minimi_content_post.created_at','DESC')
		->get();
		$article_url = array();
		if(count($query)>0){
			if($mode=="sitemap"){
				$sitemap = array();
				foreach ($query as $row) {
					$sitemap['loc'] = env('FRONTEND_URL').'article/'.$row->content_uri;
					$sitemap['last_update'] = date('c',strtotime($row->updated_at));
					array_push($article_url,$sitemap);
				}
			}else{
				foreach ($query as $row) {
					$loc = env('FRONTEND_URL').'article/'.$row->content_uri;
					array_push($article_url,$loc);
				}
			}
		}

		//video
		$query = DB::table('minimi_content_post')
			->select('content_id','minimi_content_post.updated_at')
			->leftJoin('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
			->leftJoin('minimi_product','minimi_content_post.product_id','=','minimi_product.product_id')
			->leftJoin('data_category','data_category.category_id','=','minimi_product.category_id')
			->leftJoin('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
			->leftJoin('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
			->where([
				'content_curated'=>1,
				'minimi_content_post.status'=>1,
				'content_type'=>1
			])
			->orderBy('minimi_content_post.created_at','DESC')
		->get();
		$video_url = array();
		if(count($query)>0){
			if($mode=="sitemap"){
				$sitemap = array();
				foreach ($query as $row) {
					$sitemap['loc'] = env('FRONTEND_URL').'video/'.$row->content_id;
					$sitemap['last_update'] = date('c',strtotime($row->updated_at));
					array_push($video_url,$sitemap);
				}
			}else{
				foreach ($query as $row) {
					$loc = env('FRONTEND_URL').'video/'.$row->content_id;
					array_push($video_url,$loc);
				}
			}
		}
		
		//user
		$query = DB::table('minimi_user_data')
			->select('user_uri','updated_at')
			->where([
				'active'=>1
			])
			->orderBy('minimi_user_data.created_at','DESC')
		->get();
		$user_url = array();
		if(count($query)>0){
			if($mode=="sitemap"){
				$sitemap = array();
				foreach ($query as $row) {
					$sitemap['loc'] = env('FRONTEND_URL').'u/'.$row->user_uri;
					$sitemap['last_update'] = date('c',strtotime($row->updated_at));
					array_push($user_url,$sitemap);
				}
			}else{
				foreach ($query as $row) {
					$loc = env('FRONTEND_URL').'u/'.$row->user_uri;
					array_push($user_url,$loc);
				}
			}
		}

		//category
		$query = DB::table('data_category')
			->select('category_id','updated_at')
			->where('status',1)
			->orderBy('data_category.created_at','DESC')
		->get();
		$category_url = array();
		if(count($query)>0){
			if($mode=="sitemap"){
				$sitemap = array();
				foreach ($query as $row) {
					$sitemap['loc'] = env('FRONTEND_URL').'category/'.$row->category_id;
					$sitemap['last_update'] = date('c',strtotime($row->updated_at));
					array_push($category_url,$sitemap);
				}
			}else{
				foreach ($query as $row) {
					$loc = env('FRONTEND_URL').'category/'.$row->category_id;
					array_push($category_url,$loc);
				}
			}
		}

		$return['product'] = $product_url;
		$return['article'] = $article_url;
		$return['video'] = $video_url;
		$return['user'] = $user_url;
		$return['category'] = $category_url;

		return $return;
	}
}