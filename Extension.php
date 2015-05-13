<?php

namespace Bolt\Extension\andyjessop\vimeo;

use Bolt\Events\CronEvent;
use Bolt\Events\CronEvents;

use Bolt\Application;
use Bolt\BaseExtension;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Extension extends BaseExtension
{
	const API_BASE_URL = 'https://api.vimeo.com/';

    public function initialize() {

    	$this->app['dispatcher']->addListener(CronEvents::CRON_HOURLY, array($this, 'updateVideoContent'));

    	$this->app->get('api/update_video_content', array($this, 'updateVideoContent'))
            ->bind('updateVideoContent');


        $this->app->get('api/get_videos', array($this, 'getVideosFromApi'))
            ->bind('getVideosFromApi');

    }

    public function getName()
    {
        return "vimeo";
    }

    public function updateVideoContent()
    {
        $videos = $this->getVideosFromApi();

        $cleaned = $this->cleanVideos($videos);

        $updated = $this->doVideoUpdates($cleaned);

    	$response = $this->app->json($updated);
        return $response;
    }

    public function getVideosFromApi() {

        $page = 1;
        $per_page = 5;
        $results = [];

    	$url = Extension::API_BASE_URL . 'users/' . $this->config['user_id'] . '/videos?per_page=' . $per_page . '&page=' . $page;
    	$headers = array('Authorization: Bearer ' . $this->config['access_token']);

        $part = $this->getVideosPart($url, $headers);

        foreach ($part['data'] as $data){
            array_push($results, $data);
        }

        $total = intval($part['total']);

        while ($total > $per_page)
        {
            $page += 1;

            $url = Extension::API_BASE_URL . 'users/' . $this->config['user_id'] . '/videos?per_page=' . $per_page . '&page=' . $page;
            $headers = array('Authorization: Bearer ' . $this->config['access_token']);

            $part = $this->getVideosPart($url, $headers);
            
            $count = 0;
            foreach ($part['data'] as $data){
                array_push($results, $data);
                $count++;
            }
            
            $total -= $count;
        }
       
        return $results;
    }

    public function getVideosPart($url, $headers) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL,$url);
        $result=curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($result, true);

        return $decoded;
    }

    public function cleanVideos($videos) {

        $cleanedArray = [];

        foreach ($videos as $video) {
            $cleanedVideo = new \stdClass();
            $cleanedVideo->name = $video['name'];
            $cleanedVideo->vimeo_id = $this->getIdFromUri($video['uri']);
            $cleanedVideo->description = $video['description'];
            $cleanedVideo->created_time = date_create_from_format('Y-m-d\TH:i:sP', $video['created_time'])->format('Y-m-d H:i:s');
            $cleanedVideo->modified_time = date_create_from_format('Y-m-d\TH:i:sP', $video['modified_time'])->format('Y-m-d H:i:s');
            $cleanedVideo->embed_html = $video['embed']['html'];
            array_push($cleanedArray, $cleanedVideo);
        }

        return $cleanedArray;
    }

    private function getIdFromUri($uri) {

        // get position of last /
        $position = strrpos($uri, "/");

        // get remaining characters
        $id = substr($uri, $position + 1);

        return $id;
    }

    private function doVideoUpdates($videos)
    {

        foreach($videos as $video)
        {
            $params = [ 'vimeo_id' => $video->vimeo_id ];

            // Does vimeo_id exist in content
            $exists = array_values($this->app['storage']->searchContentType('films', $params));

            $empty = $this->app['storage']->getEmptyContent('films');
            return $empty;
            $empty->id = (isset($exists[0]->id)) ? $exists[0]->id : null;
            $empty->values['id'] = (isset($exists[0]->id)) ? $exists[0]->id : null;
            $empty->values['name'] = $video->name;
            $empty->values['vimeo_id'] = $video->vimeo_id;
            $empty->values['description'] = ($video->description != null) ? $video->description : '';
            $empty->values['created_time'] = $video->created_time;
            $empty->values['modified_time'] = $video->modified_time;
            $empty->values['embed_html'] = $video->embed_html;
            $empty->values['status'] = 'published';

            // This creates new content if it doesn't already exist, otherwise just updates it
            $updated = $this->app['storage']->saveContent($empty);

        }
        
    }
    
}
