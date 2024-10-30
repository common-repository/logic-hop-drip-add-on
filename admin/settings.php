				
	<div class="logichop_settings_container">
		
		<p>	
			Getting started with your Drip integration? <a href="https://logichop.com/docs/using-logic-hop-with-drip/" target="_blank">View the instructions</a>
		</p>
		
		<h2>Drip Links</h2>
		<p>
			Append the Drip Link Query String to your Broadcast & email links to enable Drip data for users on your site.
		</p>
		
		<p>
			<em>Example:</em> <?php echo site_url(); ?>/landing-page/?drip_email={{subscriber.email}}&logichop={{subscriber.logichop}}
		<p>
		
		<p>
			<label>Drip Link Query String</label>
			<input type="text" onfocus="this.select();" readonly="readonly" value="?drip_email={{subscriber.email}}&logichop={{subscriber.logichop}}">
		</p>
		
		<p>
			Logic Hop uses cookies to access users' Drip data on future visits. To ensure consistency across devices, append the Drip Link Query String to all Drip links directed to your site.
		</p>
		
		<p>
			Additional query string parameters can be added as necessary. Append with an ampersand: <em>&utm_campaign=spring+campaign</em>
		</p>
		
		<hr>
		
		<h3>Drip Forms</h3>
		<p>
			Use the following settings for your Drip forms to enable data for new users.
		</p>
		
		<ul class="logichop-ul">
			<li>Add a <em>Post-Signup: Custom Post-Submission Page</em> with a URL to a page on your site</li>
			<li>Check <em>Post-Signup: Redirect to a post-submission page after the widget is submitted</em></li>
			<li>Check <em>Post-Signup: Send subscriber data to post-submission page</em></li>
		</ul>
		
		<p>
			Drip user data is available once the user is redirected to the custom post-submission page on your site.
		</p>
		
	</div>		
		