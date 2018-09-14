<?xml version="1.0" encoding="utf-8"?>
<rdf:RDF xmlns:dc="http://purl.org/dc/elements/1.1/"
		 xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
		 xmlns="http://purl.org/rss/1.0/">
<load file="config/config.config" cache="1" />
<foreach cond='{race} as {race_id} => {race_name}'><load file="race/{race_id}.config" cache="1" /><load file="race/{race_id}.descr.config" cache="1" /></foreach>
<channel rdf:about="{cfg_url}"> 
<title>Zordania</title>
<link>{cfg_url}</link>
<description>Zordania</description>
<dc:language>fr</dc:language>

<!-- D�claration de ressource -->
<image rdf:resource="{cfg_url}skin/metal/img/logo.png" />
	
	<items>
	 <rdf:Seq>
		<foreach cond="{histo_array} as {vars}">
		<rdf:li rdf:resource="{cfg_url}histo.html#{vars[histo_hid]}" />	
		</foreach>
	 </rdf:Seq>
	</items>
			
	</channel>
	
	<image rdf:about="{cfg_url}img/metal/logo.png">
	    <title>Zordania</title>
	    <url>{cfg_url}img/metal/logo.png</url>
	    <link>{cfg_url}</link>
	</image>

	<foreach cond="{histo_array} as {vars}">
	<item rdf:about="{cfg_url}histo.html#{vars[histo_hid]}">
		<set name="text" value="<include file='modules/histo/msg/{vars[histo_type]}.tpl' cache='1' />" />
		<title>{histo_title[{vars[histo_type]}]}</title>
		<link>{cfg_url}histo.html#{vars[histo_hid]}</link>
	
		<description>
		<math oper="strip_tags({text})" />
		</description>
		<dc:creator>{vars[mbr_pseudo]}</dc:creator>
		<dc:date>{vars[histo_date_rss]}+01:00</dc:date>
	</item>
	</foreach>

</rdf:RDF>
