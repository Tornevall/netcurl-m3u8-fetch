#!/bin/bash

# -vvideo.m3u8
# -aaudio.m3u8
# -kkeys
# -ssubs.m3u8,,
# -ooutput

php download.php -vhttps://vod-akc-eu-north-1.media.dssott.com/ps01/disney/aae80ad7-8c8d-4b5d-b06b-5355d900e622/r/composite_1200k_CENC_CTR_SD_SDR_b87c07f6-7a4c-43b8-ac8f-1b22f082ac03_919b4ddd-3741-4549-97c2-bcfd73bf4d2a.m3u8 \
	-ahttps://vod-akc-eu-north-1.media.dssott.com/ps01/disney/aae80ad7-8c8d-4b5d-b06b-5355d900e622/r/composite_64k_mp4a.40.2_en_PRIMARY_5494f969-cd9e-4ce9-9bae-6179249b7d79_919b4ddd-3741-4549-97c2-bcfd73bf4d2a.m3u8,https://vod-akc-eu-north-1.media.dssott.com/ps01/disney/aae80ad7-8c8d-4b5d-b06b-5355d900e622/r/composite_128k_mp4a.40.2_sv_PRIMARY_69de5744-b01c-489d-926b-ad72bbac5701_919b4ddd-3741-4549-97c2-bcfd73bf4d2a.m3u8 \
	-k2b351df1e9dc49a9ae69bb22443230dc:7e65da19517dde217ee5d73f7561f9be


