<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;

use DB;

class UtilityController extends Controller
{
	public function __construct()
	{
		date_default_timezone_set("Asia/Jakarta");
	}

	public function sendReq($method, $endpoint, $options = array())
	{
		$client = new Client();
		try {
			$res = $client->request($method, $endpoint, $options);
			return json_decode($res->getBody());
		} catch (RequestException $e) {
			if ($e->hasResponse()) {
				$exception = (string) $e->getResponse()->getBody();
				$exception = json_decode($exception);
				return $exception;
			}
		} catch (ConnectException $e){
			return 'connection_exception';
		}
	}

	public function sendReqFaspay($method, $endpoint, $options = array())
	{
		$client = new Client();
		try {
			$res = $client->request($method, $endpoint, $options);
			return json_decode($res->getBody()->getContents());
		} catch (RequestException $e) {
			if ($e->hasResponse()) {
				$exception = (string) $e->getResponse()->getBody();
				$exception = json_decode($exception);
				return $exception;
			}
		} catch (ConnectException $e){
			return 'connection_exception';
		}
	}

	public function searchCity(Request $request)
	{
		$data = $request->all();
		try {
			$search_query = $data['search_query'];
			$query = DB::table('data_city')
				->select('data_city.city_code', 'data_city.city_name', 'data_country.country_name')
				->join('data_country', 'data_country.country_code', '=', 'data_city.country_code')
				->where('data_city.country_code', 'ID')
				->where(function ($query) use ($search_query) {
					$query->where('city_name', 'like', $search_query . '%')
						->orWhere('country_name', 'like', $search_query . '%');
				})
				->orderBy('city_name', 'ASC')
				->get();
			if (count($query) > 0) {
				$result = array();
				foreach ($query as $row) {
					$res['city_code'] = $row->city_code;
					$res['city_name_only'] = $row->city_name;
					$res['country_name'] = $row->country_name;
					$res['city_name'] = $row->city_name . ', ' . $row->country_name;
					array_push($result, $res);
				}
				return response()->json(['code' => 200, 'message' => 'success', 'data' => $result]);
			} else {
				/*$scrapper = app('App\Http\Controllers\API\TripController')->scrapperCity($request['search']);
				if($scrapper!='cannot_found'){
					$result[0]['city_code'] = $scrapper['city_code'];
					$result[0]['city_name'] = $scrapper['city_name'].', '.$scrapper['country_name'];
					$result[0]['lat_coord'] = $row->lat_coord;
					$result[0]['lng_coord'] = $row->lng_coord;
					return response()->json(['code'=>200,'message'=>'success','data'=>$result]);
				}else{*/
				return response()->json(['code' => 4041, 'message' => 'city_not_found']);
				//}
			}
		} catch (QueryException $ex) {
			return response()->json(['code' => 4050, 'message' => 'search_city_failed']);
		}
	}

	function slug($z)
	{
		$z = strtolower($z);
		$z = preg_replace('/[^a-z0-9 -]+/', '', $z);
		$z = str_replace('  ', ' ', $z);
		$z = str_replace(' ', '-', $z);
		return trim($z, '-');
	}

	function checkUri($uri)
	{
		$return = DB::table('minimi_product')->where('product_uri', $uri)->first();
		return (empty($return)) ? "TRUE" : "FALSE";
	}

	function checkUri2($uri)
	{
		$return = DB::table('minimi_user_data')->where('user_uri', $uri)->first();
		return (empty($return)) ? "TRUE" : "FALSE";
	}

	function uri($slug)
	{
		$string = Str::random(5);
		$uri = $slug . "-" . $string;
		$check = $this->checkUri($uri);
		if ($check == "TRUE") {
			return $uri;
		} else {
			return $this->uri($slug);
		}
	}

	function uri2($slug)
	{
		$string = Str::random(5);
		$uri = $slug . "-" . $string;
		$check = $this->checkUri2($uri);
		if ($check == "TRUE") {
			return $uri;
		} else {
			return $this->uri($slug);
		}
	}

	public function upload_image($image, $destinationPath, $resize = false, $width = null, $height = null)
	{
		if ($image != null) {
			$fileName = time() . "_minimi_" . str_replace(" ", "-", $image->getClientOriginalName());
			$size = $image->getSize();
			if ($size >= 1048576) {
				$size_mb = number_format($size / 1048576, 2);
				if ($size_mb >= 20) {
					return "too_big";
				}
			}

			$allowedMimeTypes = ['image/jpeg', 'image/png'];
			$contentType = mime_content_type($image->getRealPath());

			if (!in_array($contentType, $allowedMimeTypes)) {
				return "not_an_image";
			}
			$image_resize = Image::make($image->getRealPath());
			$image_resize->orientate();
			if ($resize == true) {
				$image_resize->resize($width, $height);
			} else {
				$image_resize->resize(null, 1000, function ($constraint) {
					$constraint->aspectRatio();
					$constraint->upsize();
				});
				$image_resize->resize(1000, null, function ($constraint) {
					$constraint->aspectRatio();
					$constraint->upsize();
				});
			}
			//$image_resize->save(public_path($destinationPath.'/'.$fileName),$quality);

			$image = $image_resize->stream();

			$app_mode = (env('APP_ENV') == 'local') ? 'dev/' : '';

			$filePath = $destinationPath . '/' . $app_mode;

			Storage::disk('s3')->put($filePath . $fileName, $image->__toString(), 'public');

			$url = 'https://s3.' . env('AWS_REGION') . '.amazonaws.com/' . env('AWS_BUCKET');
			$return = $url . '/' . $filePath . urlencode($fileName);
			return $return;
		} else {
			return 'empty';
		}
	}

	public function deleteImage($image_path)
	{
		$image_path = urldecode($image_path);
		$url = 'https://s3.' . env('AWS_REGION') . '.amazonaws.com/' . env('AWS_BUCKET') . '/';
		$image = str_replace($url, '', $image_path);
		Storage::disk('s3')->delete($image);
	}

	public function altTitleImage($image_path)
	{
		$app_mode = (env('APP_ENV') == 'local') ? 'dev/' : '';
		$url = 'https://s3.' . env('AWS_REGION') . '.amazonaws.com/' . env('AWS_BUCKET') . '/public/review/product/' . $app_mode;
		$image = str_replace($url, '', $image_path);
		$image = urldecode($image);
		return $image;
	}

	public function recapperShow($product_id)
	{
		$query = DB::table('minimi_content_rating_tab')
			->where('product_id', $product_id)
			->get();
		$col_query = collect($query);

		$find = $col_query->where('tag', 'review_count')->first();
		$result['total_review'] = $find->value;

		$star = array();
		for ($i = 5; $i >= 1; $i--) {
			$find2 = $col_query->where('tag', $i . ' star')->first();
			$data['star'] = $i;
			$data['count'] = $find2->value;
			array_push($star, $data);
		}
		$result['star_count'] = $star;
		return $result;
	}

	public function ratingCounter($product_id)
	{
		$review = DB::table('minimi_content_post')
			->select('content_rating')
			->where([
				'product_id' => $product_id,
				'content_type' => 2,
				'status' => 1,
				'content_curated' => 1
			])
			->get();

		if (count($review) > 0) {
			$total = 0;
			$i = 0;
			foreach ($review as $row) {
				$total += floatval($row->content_rating);
				$i++;
			}
			$avg = floatval($total / $i);

			$update['product_rating'] = round($avg, 2);
			$update['updated_at'] = date('Y-m-d H:i:s');
			DB::table('minimi_product')->where('product_id', $product_id)->update($update);
		}
	}

	public function recapperCount($product_id, $last_date = 0)
	{
		$review = DB::table('minimi_content_post')
			->select('content_id', 'content_rating')
			->where([
				'product_id' => $product_id,
				'content_type' => 2,
				'content_counted' => 0,
				'content_curated' => 1
			])
			->get();
		$col_cont = collect($review);
		$content_ids = $col_cont->pluck('content_id')->all();
		$count_review = count($review);
		$count_rev = $this->saveTabulateResult($product_id, 'review_count', $count_review);
		$count_stars = $this->ratingStars($product_id);

		DB::table('minimi_content_post')->whereIn('content_id', $content_ids)->update([
			'content_counted' => 1,
			'updated_at' => date('Y-m-d H:i:s')
		]);

		if ($last_date == 1) {
			DB::table('minimi_product')->where('product_id', $product_id)->update([
				'last_date' => date('Y-m-d H:i:s')
			]);
		}

		$result = $this->recapperShow($product_id);
		return $result;
	}

	public function ratingStars($product_id)
	{
		$ret = array();
		for ($i = 1; $i <= 5; $i++) {
			$rating = $this->countStars($product_id, $i);
			$this->saveTabulateResult($product_id, $i . ' star', $rating['count']);
			$data['star'] = $rating['star'];
			$data['count'] = $rating['count'];
			array_push($ret, $data);
		}
		return $ret;
	}

	public function saveTabulateResult($product_id, $tag, $value)
	{
		$query = DB::table('minimi_content_rating_tab')
			->select('mcrt_id', 'value')
			->where([
				'product_id' => $product_id,
				'tag' => $tag
			])
			->first();

		$date = date('Y-m-d H:i:s');
		$save['tag'] = $tag;
		$save['updated_at'] = $date;

		if (empty($query)) {
			$save['value'] = floatval($value);
			$save['product_id'] = $product_id;
			$save['created_at'] = $date;
			DB::table('minimi_content_rating_tab')->insert($save);
			$count = $save['value'];
		} else {
			if ($value > 0) {
				$save['value'] = $query->value + floatval($value);
				DB::table('minimi_content_rating_tab')->where('mcrt_id', $query->mcrt_id)->update($save);
				$count = $save['value'];
			} else {
				$count = $query->value;
			}
		}
		return $count;
	}

	public function countStars($product_id, $star)
	{
		$star_arr = array();

		switch ($star) {
			case 1:
				$star_arr = array(1, 1.55);
				break;
			case 2:
				$star_arr = array(1.55, 2.55);
				break;
			case 3:
				$star_arr = array(2.55, 3.55);
				break;
			case 4:
				$star_arr = array(3.55, 4.55);
				break;
			case 5:
				$star_arr = array(4.55, 5);
				break;
			default:
				return false;
				break;
		}

		$query = DB::table('minimi_content_post')
			->select('minimi_content_post.content_id')
			->where('content_rating', '>', $star_arr[0])
			->where('content_rating', '<=', $star_arr[1])
			->where([
				'product_id' => $product_id,
				'content_counted' => 0,
				'content_curated' => 1
			])
			->get();

		$result['star'] = $star;
		$result['count'] = count($query);
		return $result;
	}

	public function detailReview_exe($content_id, $admin = 1)
	{
		switch ($admin) {
			case 0:
				$query = DB::table('minimi_content_post')
					->where([
						'content_id' => $content_id,
						'content_curated' => 1,
						'status' => 1
					])
					->first();
				if (empty($query)) {
					return 'empty';
				}
				break;

			case 1:
				$query = DB::table('minimi_content_post')->where('content_id', $content_id)->first();
				if (empty($query)) {
					return 'empty';
				}
				break;

			default:
				return 'undefined';
				break;
		}

		switch ($query->content_type) {
			case 1: //video
				$user = DB::table('minimi_user_data')
					->select('fullname', 'email', 'phone', 'user_uri', 'photo_profile')
					->where('user_id', $query->user_id)
					->first();

				$data['meta_title'] = $query->meta_tag;
				$data['meta_desc'] = $query->meta_desc;
				$data['content_uri'] = $query->content_uri;
				$data['content_type'] = $query->content_type;
				$data['content_curated'] = $query->content_curated;
				$data['content_text'] = $query->content_text;
				$data['content_title'] = $query->content_title;
				$data['content_rating'] = $query->content_rating;
				$data['content_embed_link'] = $query->content_embed_link;
				$data['content_video_link'] = $query->content_video_link;
				$data['content_thumbnail'] = $query->content_thumbnail;
				$data['content_published'] = date('c',strtotime($query->created_at));

				if ($query->product_id != null) {
					$product = DB::table('minimi_product')
						->select('product_name', 'brand_name', 'category_name', 'subcat_name', 'product_rating', 'prod_gallery_picture as pict')
						->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
						->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
						->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
						->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product.product_id')
						->where('minimi_product.product_id', $query->product_id)
						->first();
					if ($product == null) {
						$product = array();
					}
				} else {
					$product = array();
				}

				$return['info'] = $data;
				$return['product'] = $product;
				$return['image'] = array();
				$return['rating'] = array();
				$return['trivia'] = array();
				$return['user'] = $user;
				break;

			case 2: //review
				$user = DB::table('minimi_user_data')
					->select('fullname', 'email', 'phone', 'user_uri', 'photo_profile')
					->where('user_id', $query->user_id)
					->first();

				$data['meta_title'] = $query->meta_tag;
				$data['meta_desc'] = $query->meta_desc;
				$data['content_uri'] = $query->content_uri;
				$data['content_type'] = $query->content_type;
				$data['content_curated'] = $query->content_curated;
				$data['content_text'] = $query->content_text;
				$data['content_title'] = $query->content_title;
				$data['content_rating'] = $query->content_rating;
				$data['content_embed_link'] = $query->content_embed_link;
				$data['content_video_link'] = $query->content_video_link;
				$data['content_thumbnail'] = $query->content_thumbnail;
				$data['content_published'] = date('c',strtotime($query->created_at));

				$product = DB::table('minimi_product')
					->select('product_name', 'brand_name', 'category_name', 'subcat_name', 'product_rating', 'prod_gallery_picture as pict')
					->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
					->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
					->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
					->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product.product_id')
					->where('minimi_product.product_id', $query->product_id)
					->first();

				//trivia
				$trivia = DB::table('minimi_content_trivia')
					->select('data_trivia.trivia_question', 'data_trivia_answer.answer_content')
					->join('data_trivia', 'minimi_content_trivia.trivia_id', '=', 'data_trivia.trivia_id')
					->join('data_trivia_answer', 'minimi_content_trivia.answer_id', '=', 'data_trivia_answer.answer_id')
					->where('minimi_content_trivia.content_id', $query->content_id)
					->get();

				//rating
				$rating = DB::table('minimi_content_rating')
					->select('data_rating_param.rating_name', 'minimi_content_rating.rating_value')
					->join('data_rating_param', 'data_rating_param.rp_id', '=', 'minimi_content_rating.rp_id')
					->where('minimi_content_rating.content_id', $query->content_id)
					->get();

				//image 
				$image = DB::table('minimi_content_gallery')
					->select('cont_gallery_picture', 'cont_gallery_alt', 'cont_gallery_title')
					->where('minimi_content_gallery.content_id', $query->content_id)
					->get();

				$return['info'] = $data;
				$return['product'] = ($product == null) ? array() : $product;
				$return['image'] = $image;
				$return['rating'] = $rating;
				$return['trivia'] = $trivia;
				$return['user'] = $user;
				break;

			case 3: //article
				$data['meta_title'] = $query->meta_tag;
				$data['meta_desc'] = $query->meta_desc;
				$data['content_uri'] = $query->content_uri;
				$data['content_type'] = $query->content_type;
				$data['content_curated'] = $query->content_curated;
				$data['content_title'] = $query->content_title;
				$data['content_subtitle'] = $query->content_subtitle;
				$data['content_text'] = $query->content_text;
				$data['content_thumbnail'] = $query->content_thumbnail;
				$data['content_published'] = date('c',strtotime($query->created_at));

				$return['info'] = $data;
				break;

			case 4: //propose product &review
				$data['meta_title'] = $query->meta_tag;
				$data['meta_desc'] = $query->meta_desc;
				$data['content_uri'] = $query->content_uri;
				$data['content_type'] = $query->content_type;
				$data['content_curated'] = $query->content_curated;
				$data['content_text'] = $query->content_text;
				$data['content_title'] = '';
				$data['content_rating'] = $query->content_rating;
				$data['content_embed_link'] = $query->content_embed_link;
				$data['content_video_link'] = $query->content_video_link;
				$data['content_thumbnail'] = $query->content_thumbnail;
				$data['content_published'] = date('c',strtotime($query->created_at));

				//proposed item attribute
				$prop['category_id'] = $query->category_id;
				$prop['category_name'] = DB::table('data_category')->where('category_id', $query->category_id)->value('category_name');
				$prop['subcat_id'] = $query->subcat_id;
				$prop['subcat_name'] = DB::table('data_category_sub')->where('subcat_id', $query->subcat_id)->value('subcat_name');
				$prop['product_brand'] = $query->content_subtitle;
				$prop['product_name'] = $query->content_title;

				//user
				$user = DB::table('minimi_user_data')
					->select('fullname', 'email', 'phone', 'user_uri', 'photo_profile')
					->where('user_id', $query->user_id)
					->first();

				//trivia
				$trivia = DB::table('minimi_content_trivia')
					->select('data_trivia.trivia_question', 'data_trivia_answer.answer_content')
					->join('data_trivia', 'minimi_content_trivia.trivia_id', '=', 'data_trivia.trivia_id')
					->join('data_trivia_answer', 'minimi_content_trivia.answer_id', '=', 'data_trivia_answer.answer_id')
					->where('minimi_content_trivia.content_id', $query->content_id)
					->get();

				//rating
				$rating = DB::table('minimi_content_rating')
					->select('data_rating_param.rating_name', 'minimi_content_rating.rating_value')
					->join('data_rating_param', 'data_rating_param.rp_id', '=', 'minimi_content_rating.rp_id')
					->where('minimi_content_rating.content_id', $query->content_id)
					->get();

				//image 
				$image = DB::table('minimi_content_gallery')
					->select('cont_gallery_picture', 'cont_gallery_alt', 'cont_gallery_title')
					->where('minimi_content_gallery.content_id', $query->content_id)
					->get();

				$return['info'] = $data;
				$return['proposed_item'] = $prop;
				$return['image'] = $image;
				$return['rating'] = $rating;
				$return['trivia'] = $trivia;
				$return['user'] = $user;
				break;

			default:
				return FALSE;
				break;
		}
		return $return;
	}

	public function videoThumb($video_url)
	{
		$thumb_url = "https://i.ytimg.com/vi/{video_id}/maxresdefault.jpg";

		$exp = explode('embed/', $video_url);
		if (count($exp) == 2) {
			$video_id = substr($exp[1], 0, 11);
		} else {
			$exp = explode('v=', $video_url);
			if (count($exp) == 2) {
				$video_id = substr($exp[1], 0, 11);
			} else {
				$exp = explode('be/', $video_url);
				if (count($exp) == 2) {
					$video_id = substr($exp[1], 0, 11);
				} else {
					return FALSE;
				}
			}
		}

		$thumb = str_replace('{video_id}', $video_id, $thumb_url);
		return $thumb;
	}

	public function listReviewFull($product_id, $limit, $offset, $view_id = null)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$review = DB::table('minimi_content_post')
			->select('content_id', 'fullname', 'total_content_count', 'email', 'user_uri', 'verified', 'follower_count', 'photo_profile', 'minimi_content_post.*')
			->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
			->where([
				'minimi_content_post.product_id' => $product_id,
				'content_type' => 2,
				'minimi_content_post.status' => 1,
				'minimi_content_post.content_curated' => 1
			])
			->skip($offset_count)->take($limit)->orderBy('created_at', 'DESC')->get();
		$col_review = collect($review);
		$content_ids = $col_review->pluck('content_id')->all();
		$user_ids = $col_review->pluck('user_id')->unique()->all();

		$review_images = DB::table('minimi_content_gallery')
			->select('content_id', 'cont_gallery_picture as pict', 'cont_gallery_alt as alt', 'cont_gallery_title as title')
			->whereIn('content_id', $content_ids)
			->where('status', 1)
			->orderBy('main_poster')
			->orderBy('created_at')
			->get();
		$col_rev_img = collect($review_images);

		$review_rating = DB::table('minimi_content_rating')
			->select('content_id', 'rating_name', 'rating_value')
			->join('data_rating_param', 'data_rating_param.rp_id', '=', 'minimi_content_rating.rp_id')
			->whereIn('content_id', $content_ids)
			->get();
		$col_rev_rate = collect($review_rating);

		$return = array();
		foreach ($review as $row) {
			$followed = $this->checkFollow($view_id, $row->user_id);

			$data = array();
			$data['fullname'] = $row->fullname;
			$data['user_uri'] = $row->user_uri;
			$data['photo_profile'] = $row->photo_profile;
			$data['verified'] = $row->verified;
			$data['follower_count'] = $row->follower_count;
			$data['followed'] = $followed;
			$data['total_content_count'] = ($row->total_content_count != null) ? $row->total_content_count : 0;
			$data['content_type'] = $row->content_type;
			$data['content_id'] = $row->content_id;
			$data['content_text'] = ($row->content_text != null) ? $row->content_text : '';
			$data['content_title'] = ($row->content_title != null) ? $row->content_title : '';
			$data['content_rating'] = ($row->content_rating != null) ? round($row->content_rating) : '';
			$data['content_embed_link'] = ($row->content_embed_link != null) ? $row->content_embed_link : '';
			$data['content_video_link'] = ($row->content_video_link != null) ? $row->content_video_link : '';
			$data['content_thumbnail'] = ($row->content_thumbnail != null) ? $row->content_thumbnail : '';
			$data['created_at'] = $row->created_at;

			$user = array();
			$user['fullname'] = $row->fullname;
			$user['email'] = $row->email;
			$user['user_uri'] = $row->user_uri;
			$user['photo_profile'] = $row->photo_profile;
			$user['verified'] = $row->verified;
			$user['follower_count'] = $row->follower_count;
			$user['followed'] = $followed;

			$image = $col_rev_img->where('content_id', $row->content_id)->toArray();
			$img_arr = array();
			if (count($image) > 0) {
				foreach ($image as $img) {
					$row_img['pict'] = $img->pict;
					$row_img['alt'] = $img->alt;
					$row_img['title'] = $img->title;
					array_push($img_arr, $row_img);
				}
			}

			$rating = $col_rev_rate->where('content_id', $row->content_id)->toArray();
			$rate_arr = array();
			if (count($rating) > 0) {
				foreach ($rating as $rate) {
					$row_rate['rating_name'] = $rate->rating_name;
					$row_rate['rating_value'] = $rate->rating_value;
					array_push($rate_arr, $row_rate);
				}
			}

			$ret['info'] = $data;
			$ret['image'] = $img_arr;
			$ret['rating'] = $rate_arr;
			$ret['user'] = $user;
			array_push($return, $ret);
		}

		$next_offset = 'empty';
		if (count($review) == $limit) {
			$review2 = DB::table('minimi_content_post')
				->select('content_id')
				->where([
					'product_id' => $product_id,
					'content_type' => 2,
					'status' => 1,
					'content_curated' => 1
				])
				->skip($next_offset_count)->take($limit)->orderBy('created_at', 'DESC')->get();

			if (count($review2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $return;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listReview($limit, $offset, $content_type, $private = 0, $user_id = "")
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_content_post')
			->select('content_id', 'category_name', 'subcat_name', 'brand_name', 'product_uri', 'product_name', 'product_price', 'product_price_gb', 'fullname', 'email', 'user_uri', 'photo_profile', 'minimi_content_post.*')
			->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
			->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
			->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id');

		if ($content_type != 0) {
			$query = $query->where('content_type', $content_type);
		}

		if ($user_id != "") {
			$query = $query->where('minimi_content_post.user_id', $user_id);
		}

		if ($private == 0) {
			$query = $query->where('content_curated', 1);
		} elseif ($private == 1) {
			$query = $query->whereIn('content_curated', [0, 1, 2]);
		}

		$query = $query->where('minimi_content_post.status', 1)
			->skip($offset_count)->take($limit)->orderBy('minimi_content_post.created_at', 'DESC')->get();

		if (count($query) > 0) {
			foreach ($query as $row) {
				if($row->product_price>0){
					if($row->product_price_gb>0){
						$product_buyable = 2;
					}else{
						$product_buyable = 1;
					}
				}else{
					if($row->product_price_gb>0){
						$product_buyable = 3;
					}else{
						$product_buyable = 0;
					}
				}
	
				if($product_buyable==1){
					$row->discount = '5%';
					$row->price_before_discount = (1 + 0.05) * $row->product_price;
					$row->product_price = $row->product_price;
				}elseif($product_buyable==2){
					$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
					$row->discount = $disc.'%';
					$row->price_before_discount = $row->product_price;
					$row->product_price = $row->product_price_gb;
				}elseif($product_buyable==3){
					$row->discount = '5%';
					$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
					$row->product_price = $row->product_price_gb;
				}
			}
		}

		$next_offset = 'empty';
		if (count($query) >= $limit) {
			$query2 = DB::table('minimi_content_post')
				->select('category_name', 'subcat_name', 'brand_name', 'product_name', 'minimi_content_post.content_id')
				->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
				->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id');

			if ($content_type != 0) {
				$query2 = $query2->where('content_type', $content_type);
			}

			if ($user_id != "") {
				$query2 = $query2->where('user_id', $user_id);
			}

			if ($private == 0) {
				$query2 = $query2->where('content_curated', 1);
			} elseif ($private == 1) {
				$query2 = $query2->whereIn('content_curated', [0, 1, 2]);
			}

			$query2 = $query2->where('minimi_content_post.status', 1)
				->skip($next_offset_count)
				->take($limit)
				->orderBy('minimi_content_post.created_at', 'DESC')
				->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listReviewShort($limit, $offset, $mode=0)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_content_post')
			->select('content_id', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_text', 'content_embed_link', 'content_rating', 'minimi_content_post.created_at', 'minimi_content_rating_tab.value as review_count', 'product_price', 'product_price_gb')
			->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
			->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
			->leftJoin('minimi_content_rating_tab', 'minimi_content_rating_tab.product_id', '=', 'minimi_product.product_id')
			->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->where([
				'content_curated' => 1,
				'minimi_content_post.status' => 1,
				'minimi_content_rating_tab.tag' => "review_count",
				'content_type' => 2
			]);
		
		if($mode==1){
			$query = $query->where('minimi_product.product_price','>',0);
		}

		$query = $query->orderBy('minimi_content_post.created_at', 'DESC')
			->skip($offset_count)->take($limit)->get();

		if (count($query) > 0) {
			foreach ($query as $row) {
				if($row->product_price>0){
					if($row->product_price_gb>0){
						$product_buyable = 2;
					}else{
						$product_buyable = 1;
					}
				}else{
					if($row->product_price_gb>0){
						$product_buyable = 3;
					}else{
						$product_buyable = 0;
					}
				}
	
				if($product_buyable==1){
					$row->discount = '5%';
					$row->price_before_discount = (1 + 0.05) * $row->product_price;
					$row->product_price = $row->product_price;
				}elseif($product_buyable==2){
					$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
					$row->discount = $disc.'%';
					$row->price_before_discount = $row->product_price;
					$row->product_price = $row->product_price_gb;
				}elseif($product_buyable==3){
					$row->discount = '5%';
					$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
					$row->product_price = $row->product_price_gb;
				}
			}
		}

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('minimi_content_post')
				->select('content_id', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_text', 'content_embed_link', 'content_rating', 'minimi_content_post.created_at')
				->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
				->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
				->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->where([
					'content_curated' => 1,
					'minimi_content_post.status' => 1,
					'content_type' => 2
				]);
			if($mode==1){
				$query2 = $query2->where('minimi_product.product_price','>',0);
			}
			$query2 = $query2->orderBy('minimi_content_post.created_at', 'DESC')
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listReviewer($limit, $offset, $view_id = null)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_user_data')
			->select('user_id', 'fullname', 'user_uri', 'photo_profile', 'verified', 'total_content_count')
			->where('active', 1)
			->where('total_content_count', '>', 0)
			->orderBy('total_content_count', 'DESC')
			->skip($offset_count)->take($limit)->get();

		foreach ($query as $row) {
			$row->followed = $this->checkFollow($view_id, $row->user_id);
		}

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('minimi_user_data')
				->select('user_id', 'fullname', 'user_uri', 'photo_profile', 'verified', 'total_content_count')
				->where('active', 1)
				->where('total_content_count', '>', 0)
				->orderBy('total_content_count', 'DESC')
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listFeedbyDate($view_id=null, $arr, $offset, $limit){
		if($arr['start_date']!='empty' && $arr['end_date']!='empty'){
			$query = DB::table('minimi_content_post')
				->select('content_id','minimi_content_post.product_id','minimi_content_post.content_title', 'content_uri', 'minimi_content_post.content_subtitle','minimi_content_post.user_id','content_type','brand_name','product_name','product_price','product_price_gb','product_uri','fullname','user_uri','photo_profile','content_thumbnail','content_text','content_embed_link','content_rating','minimi_content_post.created_at')
				->leftJoin('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
				->leftJoin('minimi_product','minimi_content_post.product_id','=','minimi_product.product_id')
				->leftJoin('data_category','data_category.category_id','=','minimi_product.category_id')
				->leftJoin('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
				->leftJoin('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
				->where('minimi_content_post.created_at','>=',$arr['end_date'])
				->where('minimi_content_post.created_at','<=',$arr['start_date'])
				->where([
					'content_curated'=>1,
					'minimi_content_post.status'=>1
				])
				->inRandomOrder()
			->get();
	
			$col_prod = collect($query);
			$product_ids = $col_prod->pluck('product_id')->all();
			
			$images = DB::table('minimi_product_gallery')
				->select('product_id','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
				->whereIn('product_id',$product_ids)
				->where('main_poster',1)
			->get();
			$col_image = collect($images);
	
			$rating = DB::table('minimi_content_rating_tab')
				->select('product_id','value')
				->whereIn('product_id',$product_ids)
				->where('tag','review_count')
			->get();
			$col_rating = collect($rating);
	
			foreach ($query as $row) {
				if($row->product_id!=null && $row->product_id!=''){
					$find = $col_image->where('product_id',$row->product_id)->first();
					if($find==null){
						$row->pict = "";
						$row->alt = "";
						$row->title = "";
					}else{
						$row->pict = $find->pict;
						$row->alt = $find->alt;
						$row->title = $find->title;
					}
		
					$rating = $col_rating->where('product_id',$row->product_id)->first();
					if($rating==null){
						$row->review_count = 0;
					}else{
						$row->review_count = $rating->value;
					}
	
					$row->last_review = $this->lastReview($row->user_id, $row->product_id);
				}else{
					$row->pict = "";
					$row->alt = "";
					$row->title = "";
					$row->review_count = 0;
					$row->last_review = array();
				}
	
				$row->followed = $this->checkFollow($view_id,$row->user_id);
				
				if($row->product_price>0){
					if($row->product_price_gb>0){
						$product_buyable = 2;
					}else{
						$product_buyable = 1;
					}
				}else{
					if($row->product_price_gb>0){
						$product_buyable = 3;
					}else{
						$product_buyable = 0;
					}
				}
	
				if($product_buyable==1){
					$row->discount = '5%';
					$row->price_before_discount = (1 + 0.05) * $row->product_price;
					$row->product_price = $row->product_price;
				}elseif($product_buyable==2){
					$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
					$row->discount = $disc.'%';
					$row->price_before_discount = $row->product_price;
					$row->product_price = $row->product_price_gb;
				}elseif($product_buyable==3){
					$row->discount = '5%';
					$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
					$row->product_price = $row->product_price_gb;
				}
	
				$row->feed_type = 'content';
				$row->feed_enum = 1;
			}
	
			$query_json = json_encode($query);
			$query = json_decode($query_json);

			$created_at = DB::table('minimi_content_post')
				->leftJoin('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
				->leftJoin('minimi_product','minimi_content_post.product_id','=','minimi_product.product_id')
				->leftJoin('data_category','data_category.category_id','=','minimi_product.category_id')
				->leftJoin('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
				->leftJoin('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
				->where('minimi_content_post.created_at','<',$arr['end_date'])
				->where([
					'minimi_content_post.status'=>1,
					'content_curated'=>1
				])
				->orderBy('minimi_content_post.created_at','desc')
			->value('minimi_content_post.created_at');

			if($created_at==null){
				$result['next_date'] = 'empty';
			}else {
				$result['next_date'] = date('Y-m-d',strtotime($created_at));
			}
		}else{
			$query = array();
			$result['next_date'] = 'empty';
		}

		if($offset!=='empty'){
			$offset_count = $offset * $limit;
			$next_offset_count = ($offset+1) * $limit;
			
			$date = date('Y-m-d H:i:s');
	
			$group = DB::table('commerce_group_buy')
				->select('commerce_group_buy.*','product_name','brand_name','product_uri','product_price','product_price_gb','minimi_product_gallery.prod_gallery_picture as product_image')
				->join('minimi_product', 'minimi_product.product_id', '=', 'commerce_group_buy.product_id')
				->join('data_brand', 'minimi_product.brand_id', '=', 'data_brand.brand_id')
				->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product.product_id')
				->where([
					'minimi_product_gallery.main_poster'=>1,
					'minimi_product_gallery.status'=>1,
					'commerce_group_buy.show'=>1
				])
				->whereIn('commerce_group_buy.status',[1,2,3])
				->where('expire_at','>=',$date)
			->skip($offset_count)->limit($limit)->get();
			if(count($group)>0){
				foreach ($group as $val) {
					if($val->product_price>0){
						$disc = round((($val->product_price-$val->product_price_gb)/$val->product_price)*100);
						$val->discount = $disc.'%';
						$val->price_before_discount = $val->product_price;
						$val->product_price = $val->product_price_gb;
					}else{
						$val->discount = '5%';
						$val->price_before_discount = (1 + 0.05) * $val->product_price_gb;	
						$val->product_price = $val->product_price_gb;
					}
					$val->feed_type = 'groupbuy';
					$val->feed_enum = 2;
				}
	
				$group_json = json_encode($group);
				$group = json_decode($group_json);
				
				$query = array_merge($query, $group);
	
				shuffle($query);
	
				$group_2 = DB::table('commerce_group_buy')
					->select('commerce_group_buy.*','product_name','product_uri','product_price','product_price_gb','minimi_product_gallery.prod_gallery_picture as product_image')
					->join('minimi_product', 'minimi_product.product_id', '=', 'commerce_group_buy.product_id')
					->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product.product_id')
					->where([
						'minimi_product_gallery.main_poster'=>1,
						'minimi_product_gallery.status'=>1,
						'commerce_group_buy.show'=>1
					])
					->whereIn('commerce_group_buy.status',[1,2,3])
					->where('expire_at','>=',$date)
				->skip($next_offset_count)->limit($limit)->get();
	
				if(count($group_2)>0){
					$result['offset'] = $next_offset_count/$limit;
				}else{
					$result['offset'] = 'empty';	
				}
			}else{
				$result['offset'] = 'empty';
			}
		}else{
			$result['offset'] = 'empty';
		}

		$result['data'] = $query;
		return $result;
	}

	public function lastReview($user_id, $product_id){
		$query = DB::table('minimi_content_post')
			->select('content_id', 'content_uri','minimi_user_data.fullname', 'minimi_content_post.content_title', 'minimi_content_post.content_subtitle','minimi_content_post.user_id','content_thumbnail','content_text','content_embed_link','content_rating','minimi_content_post.created_at')
			->leftJoin('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
			->where([
				'content_curated'=>1,
				'minimi_content_post.status'=>1,
				'product_id'=>$product_id
			])
			->where('minimi_content_post.user_id','!=',$user_id)
			->orderBy('created_at', 'DESC')
		->first();

		if(empty($query)){
			return array();
		}
		
		return $query;
	}

	public function listFeed($limit, $view_id=null, $arr=array()){
		$query = DB::table('minimi_content_post')
			->select('content_id','minimi_content_post.product_id', 'minimi_content_post.content_title', 'minimi_content_post.content_subtitle','minimi_content_post.user_id','content_uri','content_type','brand_name','product_name','product_price','product_price_gb','product_uri','fullname','user_uri','photo_profile','content_thumbnail','content_text','content_embed_link','content_rating','minimi_content_post.created_at')
			->leftJoin('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
			->leftJoin('minimi_product','minimi_content_post.product_id','=','minimi_product.product_id')
			->leftJoin('data_category','data_category.category_id','=','minimi_product.category_id')
			->leftJoin('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
			->leftJoin('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
			->where([
				'content_curated'=>1,
				'minimi_content_post.status'=>1
			]);

		if(!empty($arr)){
			$query = $query->whereNotIn('content_id',$arr);
		}

		$query = $query->inRandomOrder()->take($limit)->get();

		$col_prod = collect($query);
		$content_ids = $col_prod->pluck('content_id')->all();
		$product_ids = $col_prod->pluck('product_id')->all();

		if(!empty($arr)){
			$content_ids = array_merge($content_ids, $arr);
		}
		
		$images = DB::table('minimi_product_gallery')
            ->select('product_id','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
			->whereIn('product_id',$product_ids)
			->where('main_poster',1)
		->get();
		$col_image = collect($images);

		$rating = DB::table('minimi_content_rating_tab')
			->select('product_id','value')
			->whereIn('product_id',$product_ids)
			->where('tag','review_count')
		->get();
		$col_rating = collect($rating);

		foreach ($query as $row) {
			if($row->product_id!=null && $row->product_id!=''){
				$find = $col_image->where('product_id',$row->product_id)->first();
				if($find==null){
					$row->pict = "";
					$row->alt = "";
					$row->title = "";
				}else{
					$row->pict = $find->pict;
					$row->alt = $find->alt;
					$row->title = $find->title;
				}
	
				$rating = $col_rating->where('product_id',$row->product_id)->first();
				if($rating==null){
					$row->review_count = 0;
				}else{
					$row->review_count = $rating->value;
				}
			}else{
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
				$row->review_count = 0;
			}

			$row->followed = $this->checkFollow($view_id,$row->user_id);

			if($row->product_price>0){
				if($row->product_price_gb>0){
					$product_buyable = 2;
                }else{
					$product_buyable = 1;
                }
            }else{
				if($row->product_price_gb>0){
					$product_buyable = 3;
                }else{
					$product_buyable = 0;
                }
			}

			if($product_buyable==1){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price;
				$row->product_price = $row->product_price;
            }elseif($product_buyable==2){
				$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
				$row->discount = $disc.'%';
				$row->price_before_discount = $row->product_price;
				$row->product_price = $row->product_price_gb;
			}elseif($product_buyable==3){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
				$row->product_price = $row->product_price_gb;
			}
		}

		$next_offset = 'empty';
		if(count($query)>=$limit){
			$query2 = DB::table('minimi_content_post')
				->select('content_id')
				->leftJoin('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
				->leftJoin('minimi_product','minimi_content_post.product_id','=','minimi_product.product_id')
				->leftJoin('data_category','data_category.category_id','=','minimi_product.category_id')
				->leftJoin('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
				->leftJoin('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
				->where([
					'content_curated'=>1,
					'minimi_content_post.status'=>1
				])
				->whereNotIn('content_id',$content_ids)
				->inRandomOrder()
			->take($limit)->get();

			if(count($query2)>0){
				//$next_offset = $offset+1;
				$next_offset = 'available';
			}
		}

		$result['data'] = $query;
		$result['content_ids'] = implode(',',$content_ids);
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listVideoReviewShort($limit,$offset){
		$offset_count = $offset*$limit;
		$next_offset_count = ($offset+1)*$limit;

		$query = DB::table('minimi_content_post')
			->select('content_id', 'content_uri', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_title', 'content_video_link', 'minimi_content_post.created_at')
			->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
			->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
			->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->where([
				'content_curated' => 1,
				'minimi_content_post.status' => 1,
				'content_type' => 1
			])
			->orderBy('minimi_content_post.created_at', 'DESC')
			->skip($offset_count)->take($limit)->get();

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('minimi_content_post')
				->select('content_id', 'content_uri', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_title', 'content_video_link', 'minimi_content_post.created_at')
				->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
				->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
				->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->where([
					'content_curated' => 1,
					'minimi_content_post.status' => 1,
					'content_type' => 1
				])
				->orderBy('minimi_content_post.created_at', 'DESC')
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listArticleShort($limit, $offset)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_content_post')
			->select('content_id', 'content_uri', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_title', 'minimi_content_post.created_at')
			->leftJoin('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
			->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
			->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->where([
				'content_curated' => 1,
				'minimi_content_post.status' => 1,
				'content_type' => 3
			])
			->orderBy('minimi_content_post.created_at', 'DESC')
			->skip($offset_count)->take($limit)->get();

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('minimi_content_post')
				->select('content_id', 'content_uri', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_title', 'minimi_content_post.created_at')
				->leftJoin('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
				->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
				->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->where([
					'content_curated' => 1,
					'minimi_content_post.status' => 1,
					'content_type' => 3
				])
				->orderBy('minimi_content_post.created_at', 'DESC')
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function searchBrand_exe($search_query, $limit = 6, $offset = 0)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('data_brand')
			->select('brand_id', 'brand_name', 'brand_picture')
			->where('status', 1)
			->where('brand_name', 'like', '%' . $search_query . '%')
			->skip($offset_count)->take($limit)->get();

		if (!count($query)) {
			return FALSE;
		}

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('data_brand')
				->select('brand_id', 'brand_name', 'brand_picture')
				->where('status', 1)
				->where('brand_name', 'like', '%' . $search_query . '%')
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function getBrand_exe($limit = 6, $offset = 0)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('data_brand')
			->select('brand_id', 'brand_name', 'brand_picture')
			->where('status', 1)
			->skip($offset_count)->take($limit)->get();

		if (!count($query)) {
			return FALSE;
		}

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('data_brand')
				->select('brand_id', 'brand_name', 'brand_picture')
				->where('status', 1)
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function getCategory_exe($limit = 6, $offset = 0)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('data_category')
			->select('category_id', 'category_name', 'category_desc', 'category_picture', 'category_type')
			->where('status', 1)
			->skip($offset_count)->take($limit)->get();

		if (!count($query)) {
			return FALSE;
		}

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('data_category')
				->select('category_id', 'category_name', 'category_picture')
				->where('status', 1)
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function getSubcategory_exe($category_id, $limit = 6, $offset = 0)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('data_category_sub')
			->select('subcat_id', 'subcat_name', 'subcat_picture')
			->where([
				'status' => 1,
				'category_id' => $category_id
			])
			->skip($offset_count)->take($limit)->get();

		if (!count($query)) {
			return FALSE;
		}

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('data_category_sub')
				->select('subcat_id', 'subcat_name', 'subcat_picture')
				->where([
					'status' => 1,
					'category_id' => $category_id
				])
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}


	public function check_profile_pct($user_id)
	{
		$task_id = DB::table('point_task')->where('content_tag', 'complete_profile')->value('task_id');
		$check = DB::table('point_transaction')->select('user_id', 'task_id')
			->where([
				'user_id' => $user_id,
				'task_id' => $task_id,
				'pt_trans_type' => 1,
			])
			->first();

		if (!empty($check)) {
			return FALSE;
		}

		$mandatory = DB::table('data_param')->where('param_tag', 'profile_mandatory')->value('param_value');
		$mand_array = explode(';', $mandatory);

		$user = DB::table('minimi_user_data')->where('user_id', $user_id)->first();
		$col_user = collect($user);
		$data = $col_user->only($mand_array)->all();
		$count_mandatory = count($mand_array);
		$i = 0;

		foreach ($mand_array as $row) {
			if (array_key_exists($row, $data)) {
				if ($data[$row] != null && $data[$row] != "" && $data[$row] != "1970-01-01") {
					$i++;
				}
			}
		}

		$pct = $i / $count_mandatory;

		return $pct;
	}

	public function check_profile($user_id, $data)
	{
		$task_id = DB::table('point_task')->where('content_tag', 'complete_profile')->value('task_id');
		$check = DB::table('point_transaction')->select('user_id', 'task_id')
			->where([
				'user_id' => $user_id,
				'task_id' => $task_id,
				'pt_trans_type' => 1,
			])
			->first();

		if (!empty($check)) {
			return FALSE;
		}

		$mandatory = DB::table('data_param')->where('param_tag', 'profile_mandatory')->value('param_value');
		$mand_array = explode(';', $mandatory);

		foreach ($mand_array as $row) {
			if (array_key_exists($row, $data)) {
				if ($data[$row] == null || $data[$row] == "" || $data[$row] == "1970-01-01") {
					return FALSE;
				}
			}
		}

		return TRUE;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	///// content point -start

	public function check_threshold($user_id, $content_type)
	{
		if ($content_type == 2) {
			$tag = 'review_threshold';
		} elseif ($content_type == 1) {
			$tag = 'video_review_threshold';
		} else {
			return FALSE;
		}

		$threshold = DB::table('data_param')->where('param_tag', $tag)->value('param_value');
		$period = DB::table('data_param')->where('param_tag', $tag . '_period')->value('param_value');
		$task_id = DB::table('point_task')->where('content_tag', "post_" . $content_type)->value('task_id');

		$date_start = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n'), date('j'), date('Y')));
		$date_end = date('Y-m-d H:i:s', strtotime($date_start . ' + ' . $period));

		$count = DB::table('point_transaction')
			->select('user_id', 'task_id',  DB::raw('count(task_id) as total_amount'))
			->where([
				'user_id' => $user_id,
				'task_id' => $task_id,
				'pt_trans_type' => 1,
			])
			->where('created_at', '>=', $date_start)
			->where('created_at', '<', $date_end)
			->groupBy('task_id', 'user_id')
			->get();
		if (count($count) == 0) {
			return TRUE;
		}

		if ($count[0]->total_amount < $threshold) {
			return TRUE;
		}

		return FALSE;
	}

	public function addPoint($user_id, $point, $remarks = null, $admin_id = null, $booking_id = null)
	{
		$date = date('Y-m-d H:i:s');

		$insert = array(
			'task_id' => null,
			'user_id' => $user_id,
			'pt_amount' => $point,
			'pt_trans_type' => 1,
			'content_id' => null,
			'referred_user' => null,
			'remarks' => $remarks,
			'admin_id' => $admin_id,
			'booking_id' => $admin_id,
			'created_at' => $date
		);

		DB::table('point_transaction')->insert($insert);
		$return['point'] = $this->pointCount($user_id, $date);
		$this->storePointMoengage($user_id);
		return $return;
	}

	public function removePoint($user_id, $point, $remarks = null, $admin_id)
	{
		$date = date('Y-m-d H:i:s');

		$current_point = $this->pointCount($user_id, $date);
		$this->storePointMoengage($user_id);
		if ($current_point < $point) {
			return FALSE;
		}

		$insert = array(
			'task_id' => null,
			'user_id' => $user_id,
			'pt_amount' => $point,
			'pt_trans_type' => 2,
			'content_id' => null,
			'referred_user' => null,
			'remarks' => $remarks,
			'admin_id' => $admin_id,
			'created_at' => $date
		);

		DB::table('point_transaction')->insert($insert);
		$return['point'] = $this->pointCount($user_id, $date);
		$this->storePointMoengage($user_id);
		return $return;
	}

	public function pointTransactionHistory_exe($user_id, $trans_type=0){
		$trans = DB::table('point_transaction')
			->select('pt_amount','pt_trans_type','task_id','content_id','admin_id','booking_id','referred_user','remarks','created_at')
			->where('user_id',$user_id);

		if($trans_type!=0){
			$trans = $trans->where('pt_trans_type',$trans_type);
		}

		$trans = $trans->get();

		if(count($trans)==0){
			return 'empty';
		}

		return $trans;
	}

	public function pointCounterContent($content_id, $user_id, $content_tag, $referred_user = null)
	{
		$trigger = DB::table('data_param')->where('param_tag', 'multiplier_event_trigger')->value('param_value');

		$multiplier = 1;

		if ($trigger == 1) {
			$multiplier = DB::table('data_param')->where('param_tag', 'multiplier_point')->value('param_value');
		}

		$task = DB::table('point_task')->select('task_id', 'task_name', 'task_value', 'task_type', 'task_limit')->where('content_tag', $content_tag)->first();
		$return['message'] = 'success';
		$return['point'] = 0;

		if ($task->task_type == 1) {
			$check = DB::table('point_transaction')->select('pt_id')->where(['task_id' => $task->task_id, 'user_id' => $user_id])->get();
			$count = count($check);
			if ($count >= $task->task_limit) {
				$return['message'] = 'limit_exceeded';
				return $return;
			}
		}

		$tag_query = DB::table('point_task')->select('content_tag')->where('status', 1)->get();
		$tag_col = collect($tag_query);
		$tags = $tag_col->pluck('content_tag')->all();

		if (in_array($content_tag, $tags)) {
			$remarks = $task->task_name;
		} else {
			$return['message'] = 'unknown_content_type';
			return $return;
		}

		$date = date('Y-m-d H:i:s');

		$point_amount = floatval($multiplier * $task->task_value);

		$insert = array(
			'task_id' => $task->task_id,
			'user_id' => $user_id,
			'pt_amount' => $point_amount,
			'pt_trans_type' => 1,
			'content_id' => $content_id,
			'referred_user' => $referred_user,
			'remarks' => $remarks,
			'created_at' => $date
		);

		DB::table('point_transaction')->insert($insert);
		$return['point'] = $this->pointCount($user_id,$date);
		$return['point_amount'] = $point_amount;
		$this->storePointMoengage($user_id);
		return $return;
	}


	public function pointCount($user_id, $date)
	{
		$check = DB::table('minimi_user_data')->select('point_count', 'last_count_point')->where('user_id', $user_id)->first();

		$date_start = date('Y-m-d', strtotime($check->last_count_point));

		$old_point = floatval($check->point_count);

		$point_add = floatval($this->countPoint($user_id, 1, 1, $date_start));
		$point_substract = floatval($this->countPoint($user_id, 2, 1, $date_start));
		$total_point = $point_add - $point_substract;

		$new_point = $old_point + $total_point;

		DB::table('minimi_user_data')->where('user_id', $user_id)->update([
			'point_count' => $new_point,
			'last_count_point' => $date
		]);

		return $new_point;
	}

	public function countPoint($user_id, $type, $recap, $date)
	{
		$query = DB::table('point_transaction')
			->select('user_id',  DB::raw('SUM(pt_amount) as total_amount'))
			->where('user_id', $user_id)
			->where('pt_trans_type', $type)
			->where('status', $recap)
			->where('created_at', '>=', $date)
			->groupBy('user_id')
			->first();

		if (empty($query)) {
			$total_point = 0;
		} else {
			$total_point = $query->total_amount;
			if ($recap == 1) {
				DB::table('point_transaction')
					->where('user_id', $user_id)
					->where('pt_trans_type', $type)
					->where('status', $recap)
					->where('created_at', '>=', $date)
					->update([
						'status' => 0
					]);
			}
		}

		return $total_point;
	}

	public function getTask($user_id)
	{
		$query = DB::table('point_task')->select('task_id', 'task_name', 'task_desc', 'task_image', 'task_value', 'task_type', 'content_tag')
			->where([
				'status' => 1
			])
			->orderBy('task_type')
			->get();

		$query_col = collect($query);
		$filter = $query_col->where('task_type', 1);
		$task_ids = $filter->pluck('task_id')->all();

		$check = DB::table('point_transaction')
			->select('user_id', 'task_id',  DB::raw('count(task_id) as total_amount'))
			->where('user_id', $user_id)
			->whereIn('task_id', $task_ids)
			->groupBy('task_id', 'user_id')
			->get();
		$check_col = collect($check);

		$return = array();
		foreach ($query as $key => $value) {
			$row = array();
			$row['task_name'] = $value->task_name;
			$row['task_desc'] = $value->task_desc;
			$row['task_image'] = $value->task_image;
			$row['task_value'] = $value->task_value;
			$row['task_type'] = $value->task_type;
			$row['task_uri'] = '';
			$find = $check_col->where('task_id', $value->task_id)->first();
			if ($value->task_type == 1) {
				if ($find == null) {
					if ($value->content_tag == 'complete_profile') {
						$pct = $this->check_profile_pct($user_id);
						$row['progress'] = $pct * 100;
						if ($row['progress'] == 100) {
							$this->pointCounterContent(null, $user_id, 'complete_profile');
							$row['completed'] = 1;
						} else {
							$row['completed'] = 0;
							$row['task_uri'] = '/edit-profile';
						}
					} else {
						$row['progress'] = 0;
					}
					$row['completed'] = 0;
				} else {
					$row['progress'] = 100;
					$row['completed'] = 1;
				}
			} else {
				if ($find == null) {
					$row['progress'] = 0;
				} else {
					$row['progress'] = 100;
				}
				$row['completed'] = ($find == null) ? 0 : $find->total_amount;
			}
			array_push($return, $row);
		}

		return $return;
	}

	///// content point -end
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	public function listGroupBuyProducts($limit, $offset){
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_product')
			->select('minimi_product.product_id', 'product_uri', 'product_type', 'product_name', 'product_price', 'product_price_gb', 'product_rating', 'brand_name', 'category_name', 'subcat_name')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->where([
				'data_category.status' => 1,
				'minimi_product.status'=>1
			])
			->where('product_price_gb','>',0)
			->skip($offset_count)->take($limit)->get();

		$col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();

		$images = DB::table('minimi_product_gallery')
			->select('product_id', 'prod_gallery_picture as pict', 'prod_gallery_alt as alt', 'prod_gallery_title as title')
			->whereIn('product_id', $product_ids)
			->where('main_poster', 1)
			->get();
		$col_image = collect($images);

		$rating = DB::table('minimi_content_rating_tab')
			->select('product_id', 'value')
			->whereIn('product_id', $product_ids)
			->where('tag', 'review_count')
			->get();
		$col_rating = collect($rating);

		foreach ($query as $row) {
			if($row->product_price>0){
				$discount = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
				$row->price_before_discount = $row->product_price;
				$row->product_price = $row->product_price_gb;
				$row->discount = $discount.'%';
			}else{
				$row->product_price = $row->product_price_gb;
				$row->price_before_discount = (1+0.05)*$row->product_price;
				$row->discount = '5%';
			}
			unset($row->product_price_gb);
			$find = $col_image->where('product_id', $row->product_id)->first();
			if ($find == null) {
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			} else {
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
			}

			$rating = $col_rating->where('product_id', $row->product_id)->first();
			if ($rating == null) {
				$row->review_count = 0;
			} else {
				$row->review_count = $rating->value;
			}
		}

		$next_offset = 'empty';
		if (count($query) >= $limit) {
			$query2 = DB::table('minimi_product')
				->select('product_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->where([
					'data_category.status' => 1
				])
				->where('product_price_gb','>',0)
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listProductPhys_exe($limit, $offset)
	{ //listProduct
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_product')
			->select('minimi_product.product_id', 'product_uri', 'product_type', 'product_name', 'product_price', 'product_price_gb', 'product_rating', 'brand_name', 'category_name', 'subcat_name')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->where([
				'data_category.status' => 1,
				'product_type'=>1
			])
			->orderBy('minimi_product.updated_at','DESC')
			->skip($offset_count)->take($limit)
		->get();

		$col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();

		$images = DB::table('minimi_product_gallery')
			->select('product_id', 'prod_gallery_picture as pict', 'prod_gallery_alt as alt', 'prod_gallery_title as title')
			->whereIn('product_id', $product_ids)
			->where('main_poster', 1)
			->get();
		$col_image = collect($images);

		$rating = DB::table('minimi_content_rating_tab')
			->select('product_id', 'value')
			->whereIn('product_id', $product_ids)
			->where('tag', 'review_count')
			->get();
		$col_rating = collect($rating);

		foreach ($query as $row) {
			if($row->product_price>0){
				if($row->product_price_gb>0){
					$product_buyable = 2;
                }else{
					$product_buyable = 1;
                }
            }else{
				if($row->product_price_gb>0){
					$product_buyable = 3;
                }else{
					$product_buyable = 0;
                }
			}

			if($product_buyable==1){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price;
				$row->product_price = $row->product_price;
            }elseif($product_buyable==2){
				$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
				$row->discount = $disc.'%';
				$row->price_before_discount = $row->product_price;
				$row->product_price = $row->product_price_gb;
			}elseif($product_buyable==3){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
				$row->product_price = $row->product_price_gb;
			}
			$find = $col_image->where('product_id', $row->product_id)->first();
			if ($find == null) {
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			} else {
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
			}

			$rating = $col_rating->where('product_id', $row->product_id)->first();
			if ($rating == null) {
				$row->review_count = 0;
			} else {
				$row->review_count = $rating->value;
			}
		}

		$next_offset = 'empty';
		if (count($query) >= $limit) {
			$query2 = DB::table('minimi_product')
				->select('product_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->where([
					'data_category.status' => 1,
					'product_type'=>1
				])
				->orderBy('minimi_product.updated_at','DESC')
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listProductCategory($category_id, $limit, $offset)
	{ //listProduct
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;
		$search_query = '#'.$category_id.';';

		$query = DB::table('minimi_product')
			->select('minimi_product.product_id', 'product_uri', 'product_type', 'product_name', 'product_price', 'product_price_gb', 'product_rating', 'brand_name', 'category_name', 'subcat_name')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->where([
				'data_category.status' => 1
			])
			->where(function ($query) use($category_id, $search_query){
				$query->where('minimi_product.category_id',$category_id)
					->orWhere('minimi_product.alt_category_tag','like','%'.$search_query.'%');
			})
			->skip($offset_count)->take($limit)->get();

		$col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();

		$images = DB::table('minimi_product_gallery')
			->select('product_id', 'prod_gallery_picture as pict', 'prod_gallery_alt as alt', 'prod_gallery_title as title')
			->whereIn('product_id', $product_ids)
			->where('main_poster', 1)
			->get();
		$col_image = collect($images);

		$rating = DB::table('minimi_content_rating_tab')
			->select('product_id', 'value')
			->whereIn('product_id', $product_ids)
			->where('tag', 'review_count')
			->get();
		$col_rating = collect($rating);

		foreach ($query as $row) {
			if($row->product_price>0){
				if($row->product_price_gb>0){
					$product_buyable = 2;
                }else{
					$product_buyable = 1;
                }
            }else{
				if($row->product_price_gb>0){
					$product_buyable = 3;
                }else{
					$product_buyable = 0;
                }
			}

			if($product_buyable==1){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price;
				$row->product_price = $row->product_price;
            }elseif($product_buyable==2){
				$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
				$row->discount = $disc.'%';
				$row->price_before_discount = $row->product_price;
				$row->product_price = $row->product_price_gb;
			}elseif($product_buyable==3){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
				$row->product_price = $row->product_price_gb;
			}
			$find = $col_image->where('product_id', $row->product_id)->first();
			if ($find == null) {
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			} else {
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
			}

			$rating = $col_rating->where('product_id', $row->product_id)->first();
			if ($rating == null) {
				$row->review_count = 0;
			} else {
				$row->review_count = $rating->value;
			}
		}

		$next_offset = 'empty';
		if (count($query) >= $limit) {
			$query2 = DB::table('minimi_product')
				->select('product_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->where([
					'data_category.status' => 1
				])
				->where(function ($query2) use($category_id, $search_query){
					$query2->where('minimi_product.category_id',$category_id)
						->orWhere('minimi_product.alt_category_tag','like','%'.$search_query.'%');
				})
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listProductSubcategory($subcat_id, $limit, $offset)
	{ //listProduct
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;
		$search_query = '#'.$subcat_id.';';

		$query = DB::table('minimi_product')
			->select('minimi_product.product_id', 'product_uri', 'product_name', 'product_price', 'product_price_gb', 'product_rating', 'brand_name', 'category_name', 'subcat_name')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->where([
				'data_category_sub.status' => 1,
				'data_category.status' => 1,
				'minimi_content_rating_tab.tag' => 'review_count'
			])
			->where(function ($query) use($subcat_id, $search_query){
				$query->where('minimi_product.subcat_id',$subcat_id)
					->orWhere('minimi_product.alt_subcategory_tag','like','%'.$search_query.'%');
			})
			->skip($offset_count)->take($limit)->get();

		$col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();

		$images = DB::table('minimi_product_gallery')
			->select('product_id', 'prod_gallery_picture as pict', 'prod_gallery_alt as alt', 'prod_gallery_title as title')
			->whereIn('product_id', $product_ids)
			->where('main_poster', 1)
			->get();
		$col_image = collect($images);

		$rating = DB::table('minimi_content_rating_tab')
			->select('product_id', 'value')
			->whereIn('product_id', $product_ids)
			->where('tag', 'review_count')
			->get();
		$col_rating = collect($rating);

		foreach ($query as $row) {
			if($row->product_price>0){
				if($row->product_price_gb>0){
					$product_buyable = 2;
                }else{
					$product_buyable = 1;
                }
            }else{
				if($row->product_price_gb>0){
					$product_buyable = 3;
                }else{
					$product_buyable = 0;
                }
			}

			if($product_buyable==1){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price;
				$row->product_price = $row->product_price;
            }elseif($product_buyable==2){
				$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
				$row->discount = $disc.'%';
				$row->price_before_discount = $row->product_price;
				$row->product_price = $row->product_price_gb;
			}elseif($product_buyable==3){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
				$row->product_price = $row->product_price_gb;
			}
			$find = $col_image->where('product_id', $row->product_id)->first();
			if ($find == null) {
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			} else {
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
			}

			$rating = $col_rating->where('product_id', $row->product_id)->first();
			if ($rating == null) {
				$row->review_count = 0;
			} else {
				$row->review_count = $rating->value;
			}
		}

		$next_offset = 'empty';
		if (count($query) >= $limit) {
			$query2 = DB::table('minimi_product')
				->select('product_id')
				->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->where([
					'data_category_sub.status' => 1,
					'data_category.status' => 1,
					'minimi_content_rating_tab.tag' => 'review_count'
				])	
				->where(function ($query2) use($subcat_id, $search_query){
					$query2->where('minimi_product.subcat_id',$subcat_id)
						->orWhere('minimi_product.alt_subcategory_tag','like','%'.$search_query.'%');
				})
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listReviewCategory($category_id, $limit, $offset)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_content_post')
			->select('content_id', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_text', 'content_embed_link', 'content_rating', 'minimi_content_post.created_at')
			->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
			->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
			->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->where([
				'minimi_content_post.category_id' => $category_id,
				'content_curated' => 1,
				'minimi_content_post.status' => 1,
				'content_type' => 2
			])
			->orderBy('minimi_content_post.created_at', 'DESC')
			->skip($offset_count)->take($limit)->get();

		$next_offset = 'empty';
		if (count($query) == $limit) {
			$query2 = DB::table('minimi_content_post')
				->select('content_id', 'brand_name', 'product_name', 'product_uri', 'fullname', 'user_uri', 'photo_profile', 'content_thumbnail', 'content_text', 'content_embed_link', 'content_rating', 'minimi_content_post.created_at')
				->join('minimi_user_data', 'minimi_user_data.user_id', '=', 'minimi_content_post.user_id')
				->leftJoin('minimi_product', 'minimi_content_post.product_id', '=', 'minimi_product.product_id')
				->leftJoin('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->where([
					'content_curated' => 1,
					'minimi_content_post.status' => 1,
					'content_type' => 2
				])
				->orderBy('minimi_content_post.created_at', 'DESC')
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;

		return $result;
	}

	public function listProductBrand($brand_id, $limit, $offset)
	{ //listProduct
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_product')
			->select('minimi_product.product_id', 'product_uri', 'product_name', 'product_price', 'product_price_gb', 'product_rating', 'brand_name', 'category_name', 'subcat_name')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->where([
				'minimi_product.brand_id' => $brand_id,
				'data_brand.status' => 1,
				'minimi_content_rating_tab.tag' => 'review_count'
			])
		->skip($offset_count)->take($limit)->get();

		$col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();

		$images = DB::table('minimi_product_gallery')
			->select('product_id', 'prod_gallery_picture as pict', 'prod_gallery_alt as alt', 'prod_gallery_title as title')
			->whereIn('product_id', $product_ids)
			->where('main_poster', 1)
			->get();
		$col_image = collect($images);

		$rating = DB::table('minimi_content_rating_tab')
			->select('product_id', 'value')
			->whereIn('product_id', $product_ids)
			->where('tag', 'review_count')
			->get();
		$col_rating = collect($rating);

		foreach ($query as $row) {
			if($row->product_price>0){
				if($row->product_price_gb>0){
					$product_buyable = 2;
                }else{
					$product_buyable = 1;
                }
            }else{
				if($row->product_price_gb>0){
					$product_buyable = 3;
                }else{
					$product_buyable = 0;
                }
			}

			if($product_buyable==1){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price;
				$row->product_price = $row->product_price;
            }elseif($product_buyable==2){
				$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
				$row->discount = $disc.'%';
				$row->price_before_discount = $row->product_price;
				$row->product_price = $row->product_price_gb;
			}elseif($product_buyable==3){
				$row->discount = '5%';
				$row->price_before_discount = (1 + 0.05) * $row->product_price_gb;
				$row->product_price = $row->product_price_gb;
			}
			$find = $col_image->where('product_id', $row->product_id)->first();
			if ($find == null) {
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			} else {
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
			}

			$rating = $col_rating->where('product_id', $row->product_id)->first();
			if ($rating == null) {
				$row->review_count = 0;
			} else {
				$row->review_count = $rating->value;
			}
		}

		$next_offset = 'empty';
		if (count($query) >= $limit) {
			$query2 = DB::table('minimi_product')
				->select('product_id')
				->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
				->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
				->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
				->where([
					'minimi_product.brand_id' => $brand_id,
					'data_brand.status' => 1,
					'minimi_content_rating_tab.tag' => 'review_count'
				])
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listCollection($limit = 2, $offset = 0)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;

		$query = DB::table('minimi_product_collection')
			->select('collection_id', 'collection_name', 'collection_desc','collection_uri')
			->where([
				'minimi_product_collection.status' => 1,
				'minimi_product_collection.show' => 1
			])
			->skip($offset_count)->take($limit)->get();

		$col_colls = collect($query);
		$collection_ids = $col_colls->pluck('collection_id')->all();

		$offset_item = 0;
		$limit_item = 6;
		$item_next_offset_count = ($offset_item + 1) * $limit_item;

		$item = DB::table('minimi_product_collection_item')
			->select('collection_id', 'minimi_product_collection_item.product_id', 'minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product.product_price', 'minimi_product.product_price_gb', 'minimi_product.product_rating', 'data_category.category_name', 'data_category_sub.subcat_name', 'data_brand.brand_name', 'prod_gallery_picture as pict')
			->join('minimi_product', 'minimi_product.product_id', '=', 'minimi_product_collection_item.product_id')
			->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product_collection_item.product_id')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->whereIn('minimi_product_collection_item.collection_id', $collection_ids)
			->where([
				'minimi_product_collection_item.status' => 1,
				'minimi_product.status' => 1,
				'minimi_product_gallery.main_poster' => 1
			])
			->get();
		foreach ($item as $itm) {
			if($itm->product_price_gb>0){
				if($itm->product_price>0){
					$disc = round((($itm->product_price-$itm->product_price_gb)/$itm->product_price)*100);
					$itm->discount = $disc.'%';
					$itm->price_before_discount = $itm->product_price;
					$itm->product_price = $itm->product_price_gb;
				}else{
					$itm->discount = '5%';
					$itm->price_before_discount = (1 + 0.05) * $itm->product_price_gb;	
					$itm->product_price = $itm->product_price_gb;
				}
			}else{
				$itm->discount = '5%';
				$itm->price_before_discount = (1 + 0.05) * $itm->product_price;
			}
		}
		$col_item = collect($item);

		foreach ($query as $row) {
			$row->url = env('FRONTEND_URL').'hot-products/'.$row->collection_uri;
			$col_items = $col_item->where('collection_id', $row->collection_id);
			$collection = $col_items->slice($offset_item, $limit_item)->all();
			$row->collection_item = array_values($collection);
			$next_offset_item = $col_items->slice($item_next_offset_count, $limit_item)->all();
			$row->offset = (count($next_offset_item) > 0) ? 1 : 'empty';
		}

		$next_offset = 'empty';
		if (count($query) >= $limit) {
			$query2 = DB::table('minimi_product_collection')
				->select('collection_id')
				->where([
					'minimi_product_collection.status' => 1,
					'minimi_product_collection.show' => 1
				])
				->skip($next_offset_count)->take($limit)->get();

			if (count($query2) > 0) {
				$next_offset = $next_offset_count / $limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
	}

	public function listProductRand($limit)
	{
		$query = DB::table('minimi_product')
			->select('minimi_product.product_id', 'product_uri', 'product_type', 'product_name', 'product_price', 'product_price_gb', 'product_rating', 'brand_name', 'category_name', 'subcat_name')
			->leftJoin('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->leftJoin('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->where([
				'data_category.status' => 1,
				'product_type'=>1
			])
			->where(function ($query){
				$query->where('product_price','>',1)
					->orWhere('product_price_gb','>',0);
			})
		->inRandomOrder()->limit($limit)->get();

		$col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();

		$images = DB::table('minimi_product_gallery')
			->select('product_id', 'prod_gallery_picture as pict', 'prod_gallery_alt as alt', 'prod_gallery_title as title')
			->whereIn('product_id', $product_ids)
			->where('main_poster', 1)
			->get();
		$col_image = collect($images);

		$rating = DB::table('minimi_content_rating_tab')
			->select('product_id', 'value')
			->whereIn('product_id', $product_ids)
			->where('tag', 'review_count')
			->get();
		$col_rating = collect($rating);

		$return = array();
		foreach ($query as $row) {
			$arr = array();
			$arr['product_id'] = $row->product_id;
			$arr['product_uri'] = $row->product_uri;
			$arr['product_type'] = $row->product_type;
			$arr['product_name'] = $row->product_name;
			$arr['product_rating'] = $row->product_rating;
			$arr['brand_name'] = $row->brand_name;
			$arr['category_name'] = $row->category_name;
			$arr['subcat_name'] = $row->subcat_name;
			
			if($row->product_price>0){
				if($row->product_price_gb>0){
					$arr['product_buyable'] = 2;
                }else{
					$arr['product_buyable'] = 1;
                }
            }else{
				if($row->product_price_gb>0){
					$arr['product_buyable'] = 3;
                }else{
					$arr['product_buyable'] = 0;
                }
			}

			if($arr['product_buyable']==1){
				$arr['discount'] = '5%';
				$arr['price_before_discount'] = (1 + 0.05) * $row->product_price;
				$arr['product_price'] = $row->product_price;
            }elseif($arr['product_buyable']==2){
				$disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
				$arr['discount'] = $disc.'%';
				$arr['price_before_discount'] = $row->product_price;
				$arr['product_price'] = $row->product_price_gb;
			}elseif($arr['product_buyable']==3){
				$arr['discount'] = '5%';
				$arr['price_before_discount'] = (1 + 0.05) * $row->product_price_gb;
				$arr['product_price'] = $row->product_price_gb;
			}

			$find = $col_image->where('product_id', $row->product_id)->first();
			if ($find == null) {
				$arr['pict'] = "";
				$arr['alt'] = "";
				$arr['title'] = "";
			} else {
				$arr['pict'] = $find->pict;
				$arr['alt'] = $find->alt;
				$arr['title'] = $find->title;
			}

			$rating = $col_rating->where('product_id', $row->product_id)->first();
			if ($rating == null) {
				$arr['review_count'] = 0;
			} else {
				$arr['review_count'] = $rating->value;
			}
			array_push($return,$arr);
		}

		return $return;
	}

	public function detailCollection_exe($collection_id, $limit, $offset, $sorting)
	{
		$offset_count = $offset * $limit;
		$next_offset_count = ($offset + 1) * $limit;
		$sorting_arr = explode(';',$sorting);

		$query = DB::table('minimi_product_collection')
			->select('collection_id', 'collection_name', 'collection_desc','collection_uri')
			->where([
				'minimi_product_collection.status' => 1,
				'minimi_product_collection.show' => 1
			])
			->where(function ($query) use ($collection_id){
				$query->where('collection_id',$collection_id)
					->orWhere('collection_uri',$collection_id);
			})
			->first();

		if (empty($query)) {
			return 'empty';
		}

		$item = DB::table('minimi_product_collection_item')
			->select('minimi_product_collection_item.product_id', 'minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product.product_price', 'minimi_product.product_price_gb', 'minimi_product.product_rating', 'data_category.category_name', 'data_category_sub.subcat_name', 'data_brand.brand_name', 'prod_gallery_picture as pict')
			->join('minimi_product', 'minimi_product.product_id', '=', 'minimi_product_collection_item.product_id')
			->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product_collection_item.product_id')
			->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
			->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
			->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
			->where([
				'minimi_product_collection_item.collection_id' => $query->collection_id,
				'minimi_product_collection_item.status' => 1,
				'minimi_product.status' => 1,
				'minimi_product_gallery.main_poster' => 1
			])
		->skip($offset_count)->take($limit);
		
		foreach($sorting_arr as $row){
			$row_arr = explode(':',$row);
			$item = $item->orderBy($row_arr[0],$row_arr[1]);
		}
		
		$item = $item->get();

		$query->url = env('FRONTEND_URL').'hot-products/'.$query->collection_uri;

		$next_offset = 'empty';
		if (count($item) > 0) {
			foreach ($item as $itm) {
				if($itm->product_price_gb>0){
					if($itm->product_price>0){
						$disc = round((($itm->product_price-$itm->product_price_gb)/$itm->product_price)*100);
						$itm->discount = $disc.'%';
						$itm->price_before_discount = $itm->product_price;
						$itm->product_price = $itm->product_price_gb;
					}else{
						$itm->discount = '5%';
						$itm->price_before_discount = (1 + 0.05) * $itm->product_price_gb;	
						$itm->product_price = $itm->product_price_gb;
					}
				}else{
					$itm->discount = '5%';
					$itm->price_before_discount = (1 + 0.05) * $itm->product_price;
				}
			}

			$query->collection_item = $item;

			if (count($item) >= $limit) {
				$item2 = DB::table('minimi_product_collection_item')
					->select('minimi_product_collection_item.product_id', 'minimi_product.product_uri')
					->join('minimi_product', 'minimi_product.product_id', '=', 'minimi_product_collection_item.product_id')
					->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product_collection_item.product_id')
					->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
					->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
					->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
					->where([
						'minimi_product_collection_item.collection_id' => $query->collection_id,
						'minimi_product_collection_item.status' => 1,
						'minimi_product.status' => 1,
						'minimi_product_gallery.main_poster' => 1
					])
				->skip($next_offset_count)->take($limit);
					
				foreach($sorting_arr as $row){
					$row_arr = explode(':',$row);
					$item2 = $item2->orderBy($row_arr[0],$row_arr[1]);
				}

				$item2 = $item2->get();

				if (count($item2) > 0) {
					$next_offset = $next_offset_count / $limit;
				}
			}
		} else {
			$query->collection_item = array();
		}

		$result['data'] = $query;
		$result['offset_item'] = $next_offset;
		return $result;
	}

	public function updateProductPriceGB($product_id)
	{
		$variant = DB::table('minimi_product_variant')
			->select('variant_id', 'stock_price_gb')
			->where('product_id', $product_id)
			->where('publish',1)
			->where('status',1)
			->orderBy('stock_price_gb', 'ASC')
		->first();

		if($variant!=null){
			DB::table('minimi_product')->where('product_id', $product_id)->update([
				'product_price_gb' => $variant->stock_price_gb,
				'gb_minimal' => 3,
				'gb_expire_period' => '1 Days',
				'updated_at' => date('Y-m-d H:i:s')
			]);
		}else{
			DB::table('minimi_product')->where('product_id', $product_id)->update([
				'product_price_gb' => 0,
				'gb_minimal' => null,
				'gb_expire_period' => null,
				'updated_at' => date('Y-m-d H:i:s')
			]);
		}
	}

	public function updateProductPrice($product_id)
	{
		$variant = DB::table('minimi_product_variant')
			->select('variant_id', 'stock_price')
			->where('product_id', $product_id)
			->where('publish',1)
			->where('status',1)
			->orderBy('stock_price', 'ASC')
		->first();

		if($variant!=null){
			DB::table('minimi_product')->where('product_id', $product_id)->update([
				'product_price' => $variant->stock_price,
				'updated_at' => date('Y-m-d H:i:s')
			]);
		}else{
			DB::table('minimi_product')->where('product_id', $product_id)->update([
				'product_price' => 0,
				'updated_at' => date('Y-m-d H:i:s')
			]);
		}
	}

	public function cronProductScan(){
		$query = DB::table('minimi_product')
			->select('product_id')
			->where('product_price','>',0)
		->get();

		$col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();

		$item = DB::table('minimi_product_variant')
			->select('variant_id','minimi_product_variant.product_id','stock_count','stock_price','stock_price_gb')
			->join('minimi_product', 'minimi_product.product_id', '=', 'minimi_product_variant.product_id')
			->whereIn('minimi_product_variant.product_id', $product_ids)
			->where('minimi_product_variant.status',1)
		->orderBy('product_id')->get();

		$product_id = '';
		$count = 0;
		$count_0 = 0;
		$arr = array();
		$arr2 = array();
		foreach ($item as $itm) {
			$data = array();
			if($product_id!=$itm->product_id){
				if($count>0){
					if($count_0==$count){
						array_push($arr, $product_id);
					}
				}
				$product_id = $itm->product_id;
				$count = 0;
				$count_0 = 0;
			}

			if($itm->stock_price==0){
				$count_0++;
			}
			$count++;
		}
		
		foreach ($arr as $value) {
			echo $value."<br>";
			$this->updateProductPrice($value);
			$this->updateProductPriceGB($value);
		}
	}

	public function cronOrderScan(){
		$date = date('Y-m-d H:i:s');
		$expire_date = date('Y-m-d H:i',strtotime("-24 hours"));

		$query = DB::table('commerce_booking')
			->select('order_id')
			->whereIn('paid_status',[0,3,4])
			->where('created_at','<=',$expire_date)
		->get();

		foreach ($query as $row) {
			$update['paid_status'] = 2;
			$update['cancel_status'] = 1;
			$update['updated_at'] = $date;
			DB::table('commerce_booking')->where('order_id',$row->order_id)->update($update);
			app('App\Http\Controllers\Utility\PaymentController')->payment_cancel($row->order_id);
		}
	}

	public function countTransactionPoint($price_amount)
	{
		$param = DB::table('data_param')
			->select('param_tag','param_value')
			->whereIn('param_tag',['transaction_point_percentage','point_value','transaction_point_threshold','transaction_point_minimum'])
		->get();
		$col_param = collect($param);

		$transaction_point_percentage = floatval($col_param->firstWhere('param_tag', 'transaction_point_percentage')->param_value);
		$transaction_point_threshold = floatval($col_param->firstWhere('param_tag', 'transaction_point_threshold')->param_value);
		$transaction_point_minimum = floatval($col_param->firstWhere('param_tag', 'transaction_point_minimum')->param_value);
		$point_value = floatval($col_param->firstWhere('param_tag', 'point_value')->param_value);
		if($price_amount>=$transaction_point_minimum){
	
			$reward = floatval(round(($transaction_point_percentage/100)*$price_amount));
	
			$point = round($reward/$point_value);
			if($point>0 && $point<1){
				$point = 1;
			}

			if($point>$transaction_point_threshold){
				$point = $transaction_point_threshold;
			}
		}else{
			$point = 0;
		}
		
		return $point;
	}

	public function storePointMoengage($user_id)
	{
		$user = DB::table('minimi_user_data')->where('user_id', $user_id)->first();

		$form_data = [
			'type' => 'customer',
			'customer_id' => $user_id,
			'attributes' => array(
				'poin' => $user->point_count
			)
		];

		$app_id = env('MOENGAGE_APPID');
		$app_secret = env('MOENGAGE_API_KEY');
		$token = base64_encode($app_id . ':' . $app_secret);

		//$options['json'] = json_encode($form_data);
		$options['json'] = $form_data;
		$options['headers'] = array(
			'Authorization' => 'Basic ' . $token,
			//'Content-Type'=>'application/json',
			'MOE-APPKEY' => $app_id
		);
		$endpoint = env('MOENGAGE_API_ENDPOINT') . 'v1/customer/' . $app_id;

		$return = $this->sendReq('POST', $endpoint, $options);
	}

	public function curlOriginSicepat()
	{
		$options['headers'] = array(
			'api-key' => env('SICEPAT_API_TRACKING_KEY')
		);
		$endpoint = env('SICEPAT_API_ENDPOINT_TRACKING') . 'customer/origin';

		$return = $this->sendReq('GET', $endpoint, $options);

		return $return;
	}

	public function curlDestinationSicepat()
	{
		$options['headers'] = array(
			'api-key' => env('SICEPAT_API_TRACKING_KEY')
		);
		$endpoint = env('SICEPAT_API_ENDPOINT_TRACKING') . 'customer/destination';

		$return = $this->sendReq('GET', $endpoint, $options);

		return $return;
	}

	public function curlTariffSicepat($origin, $destination, $weight)
	{
		$options['headers'] = array(
			'api-key' => env('SICEPAT_API_TRACKING_KEY')
		);

		$options['query'] = array(
			'origin' => $origin,
			'destination' => $destination,
			'weight' => $weight
		);
		$endpoint = env('SICEPAT_API_ENDPOINT_TRACKING') . 'customer/tariff';

		$return = $this->sendReq('GET', $endpoint, $options);

		return $return;
	}

	public function curlSicepatPickupRequest($param)
	{
		$options['headers'] = array(
			'Content-type' => 'application/json; charset=utf-8',
			'Accept' => 'application/json'
		);

		$options['json'] = $param;

		$endpoint = env('SICEPAT_API_ENDPOINT').'api/partner/requestpickuppackage';
		$return = $this->sendReq('POST', $endpoint, $options);

		return $return;
	}

	public function curlTrackingWaybillSicepat($waybill)
	{
		$options['headers'] = array(
			'api-key' => env('SICEPAT_API_TRACKING_KEY')
		);

		$options['query'] = array(
			'waybill' => $waybill
		);

		$endpoint = env('SICEPAT_API_ENDPOINT_TRACKING') . 'customer/waybill';

		$return = $this->sendReq('GET', $endpoint, $options);

		return $return;
	}

	public function sendMail($array)
	{
		$email = trim($array['receiver_email']);
		$subject = $array['subject'];
		$template = $array['template'];
		$from_address = env('MAIL_FROM_ADDRESS');
		$from_name = env('MAIL_FROM_NAME');
		Mail::send($template, $array['data'], function ($message) use ($email, $subject, $from_address, $from_name) {
			$message->from($from_address, $from_name);
			$message->to($email);
			$message->subject($subject);
		});

		return response()->json(['code' => 200, 'message' => 'Request completed']);
	}

	public function storeEventMoengage($user_id, $actions){
		$form_data = [
			'type' => 'event',
			'customer_id' => $user_id,
			'actions' => $actions
		];

		$app_id = env('MOENGAGE_APPID');
		$app_secret = env('MOENGAGE_API_KEY');
		$token = base64_encode($app_id.':'.$app_secret);

		//$options['json'] = json_encode($form_data);
		$options['json'] = $form_data;
		$options['headers'] = array(
			'Authorization'=>'Basic '.$token,
			//'Content-Type'=>'application/json',
			'MOE-APPKEY'=>$app_id
		);
		$endpoint = env('MOENGAGE_API_ENDPOINT').'v1/event/'.$app_id;

		$return = $this->sendReq('POST',$endpoint,$options);
		return $return;
	}

	public function storeEventAppsflyer($user_id, $actions){
		$app_id = env('APPSFLYER_BUNDLE_ID');
		$app_key = env('APPSFLYER_DEV_KEY');

		$act = $actions[0];

		$form_data = [
			'webDevKey' => $app_key,
			'customerUserId' => strval($user_id),
			'eventType' => 'EVENT',
			'eventName' => $act['action'],
			'timestamp' => strtotime($act['attributes']['payment_date']),
			'eventRevenueCurrency' => 'IDR',
			'eventRevenue' => $act['attributes']['grand_total'],
			'eventValue' => array("purchase"=>$act['attributes'])
		];

		$options['json'] = $form_data;
		$options['headers'] = array(
			'Accept-Encoding'=>'application/json',
			'Content-Type'=>'application/json'
		);
		$endpoint = env('APPSFLYER_API_ENDPOINT').$app_id.'/event';
		$return = $this->sendReq('POST',$endpoint,$options);
		return $return;
	}

	public function checkFollow($user_id, $follow_id){
		if($follow_id == null || $user_id == null){
			return 0;
		}

		$query = DB::table('data_user_follow')->where(['user_id' => $user_id, 'follow_id' => $follow_id, 'status' => 1])->first();
		if (!empty($query)) {
			return 1;
		} else {
			return 0;
		}
	}

	public function dateInBahasa($date, $type=1){
		$month = date('n', strtotime($date));
		switch ($month) {
			case 1:
				$month_name = 'Januari';
			break;
			case 2:
				$month_name = 'Februari';
			break;
			case 3:
				$month_name = 'Maret';
			break;
			case 4:
				$month_name = 'April';
			break;
			case 5:
				$month_name = 'Mei';
			break;
			case 6:
				$month_name = 'Juni';
			break;
			case 7:
				$month_name = 'Juli';
			break;
			case 8:
				$month_name = 'Agustus';
			break;
			case 9:
				$month_name = 'September';
			break;
			case 10:
				$month_name = 'Oktober';
			break;
			case 11:
				$month_name = 'November';
			break;
			default:
				$month_name = 'Desember';
			break;
		}

		if($type==1){
			$day = date('w',strtotime($date));
			switch ($day) {
				case 0:
					$day_name = 'Minggu';
					break;
				case 1:
					$day_name = 'Senin';
					break;
				case 2:
					$day_name = 'Selasa';
					break;
				case 3:
					$day_name = 'Rabu';
					break;
				case 4:
					$day_name = 'Kamis';
					break;
				case 5:
					$day_name = 'Jumat';
					break;
				default:
					$day_name = 'Sabtu';
					break;
			}
			return $day_name.', '.date('j',strtotime($date)).' '.$month_name.' '.date('Y',strtotime($date)).', '.date('H:i',strtotime($date));
		}

		return date('j',strtotime($date)).' '.$month_name.' '.date('Y',strtotime($date));
	}

	public function replicateError($mode){
		$error = floatval($mode);
		abort($error);
	}
}
