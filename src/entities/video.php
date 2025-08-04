<?php
	class Video
	{
    	public $Id;
    	public $Titulo;
		public $IdCanal;
		public $Canal;
		public $Url;
		public $ThumbnailUrl;
    	public $DataPublicacao;
		public $DataPublicacaoCurto;
    	public $Duracao;
		public $DuracaoEmSegundos;
    	public $Views;
		public $ViewsCurto;
    	public $Likes;
		public $Plataforma;
		public $Tags;

		public function round_views( $views )
		{
			$units = ["", "mil", "mi", "bi", "ti"];
			$number = intval($views);
			$exponent = floor(log($number) / log(1000));
	
			return round($number / pow(1000, $exponent)) . " " .$units[$exponent];
		}

		public function convert_time_format($time)
		{
			$interval = new DateInterval($time);
		
			if ($interval->h > 0) {
				return $interval->format("%H:%I:%S");
			} else {
				return $interval->format("%I:%S");
			}
		}

		public function round_time( $time )
		{
			$interval = new DateInterval( $time );
			$minutos = $interval->i;
			$segundos = $interval->s;
			$total_minutos = $minutos + ($segundos / 60);

			switch (true)
			{
				case ( $total_minutos <= 5.99 ):
					return "5";
					break;
					
				case ( $total_minutos <= 10.99 ):
					return "5-10";
					break;

				case ( $total_minutos <= 15.99 ):
					return "10-15";
					break;

				case ( $total_minutos <= 20.99 ):
					return "15-20";
					break;

				default:
					return "20";
			}
		}

		public function convert_to_local_time( $date )
		{
			$timezone = new DateTimeZone("America/Sao_Paulo");
			$datetime = date_create_from_format("Y-m-d\TH:i:s\Z", $date);
			$datetime->setTimezone($timezone);
  
			return date_format($datetime, "Y-m-d");
		}

		public function format_date_pt( $date )
		{
			setlocale(LC_TIME, "pt_BR", "pt_BR.utf-8", "portuguese");
			$formatted_date = strftime("%b %Y", strtotime($date));
			return ucfirst($formatted_date);
		}

		public function convert_duration_to_seconds($duration)
		{
			$interval = new DateInterval($duration);
			$seconds = ($interval->d * 86400) +
					   ($interval->h * 3600) +
					   ($interval->i * 60) +
					   $interval->s;
			return $seconds;
		}

		public function youtube_video_converter( $video_data )
		{
			$this->Id = $video_data['items'][0]['id'];

			$this->Titulo = $video_data['items'][0]['snippet']['title'];
			$this->Url = 'https://www.youtube.com/watch?v=' . $this->Id;
			$this->ThumbnailUrl = $video_data['items'][0]['snippet']['thumbnails']['maxres']['url'];
			$this->Views = $video_data['items'][0]['statistics']['viewCount'];
			$this->Likes = $video_data['items'][0]['statistics']['likeCount'];
		
			$this->IdCanal = $video_data['items'][0]['snippet']['channelId'];
			$this->Canal = $video_data['items'][0]['snippet']['channelTitle'];

			$this->ViewsCurto = $this->round_views( $this->Views );
			
			$durationISO = $video_data['items'][0]['contentDetails']['duration'];
			$this->Duracao = $this->convert_time_format( $durationISO );
			$this->DuracaoEmSegundos = strval($this->convert_duration_to_seconds( $durationISO ));

			$this->DataPublicacao = $this->convert_to_local_time( $video_data['items'][0]['snippet']['publishedAt'] );
			$this->DataPublicacaoCurto = $this->format_date_pt( $this->DataPublicacao );
			
			$this->Plataforma = "Youtube";
			
			// Processa as tags do vídeo
			$this->Tags = $this->process_video_tags($video_data['items'][0]['snippet']);
		}
		
		/**
		 * Processa as tags do vídeo do YouTube
		 */
		private function process_video_tags($snippet)
		{
			// Retorna as tags exatamente como vêm do YouTube
			return isset($snippet['tags']) && is_array($snippet['tags']) ? $snippet['tags'] : [];
		}
	}
?> 