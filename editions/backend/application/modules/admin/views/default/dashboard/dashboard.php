</div>
<!-- https://fontawesome.com/v4.7.0/icons/ -->
<style>
	.panel-purple {
		color: #ffffff;
		background-color: #6a47aa;
		border-color: #6a47aa;
	}
</style>
<!-- /#page-wrapper -->



<div id="dashboard">
	<div id="page-wrapper">
		<div class="row">
			<div class="col-lg-12">
				<h1 class="page-header"><i class="fa fa-tachometer-alt fa-fw"></i> Dashboard</h1>
			</div>
		</div>
		<div class="row">
			<?php if ($this->session->flashdata('message')) : ?>
				<div class="col-lg-12 col-md-12">
					<div class="alert <?= $this->session->flashdata('message-kind') ?>  alert-dismissable">
						<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
						<?= $this->session->flashdata('message') ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="col-lg-3 col-md-6">
				<div class="panel panel-default">
					<div class="panel-heading">
						<div class="row">
							<div class="col-xs-3">
								<i class="fa fa-users fa-3x"></i>
							</div>
							<div class="col-xs-9 text-right">
								<div>{{animated_users_count | prettydecimal(0)}} Users <i class="fa fa-users fa-1x"></i></div>
							</div>
						</div>
					</div>
					<a href="#" v-on:click="show_users = !show_users">
						<div class="panel-footer">
							<span class="pull-left">
								<span v-if="!show_users">Show users</span>
								<span v-else>Hide users</span>
							</span>
							<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
							<div class="clearfix"></div>
						</div>
					</a>
				</div>
			</div>
		</div>
		<div class="row" v-if="show_users">
			<div v-for="user in users" class="col-lg-2 col-md-2">
				<div class="panel panel-white">
					<div class="panel-heading">
						<div class="row">
							<div class="col-xs-3">
								<img :src="user | user_image_url" class="circular--square" with="48px" height="48px" />
							</div>
							<div class="col-xs-9 text-right">
								<a :href="user | user_edit_url">
									<div> {{user.first_name | user_short}}</div>
									<div> {{user.last_name | user_short}}</div>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<style>
	.panel-purple {
		color: #ffffff;
		background-color: #6a47aa;
		border-color: #6a47aa;
	}

	.panel-white {
		color: #ffffff;
		background-color: #ffffff;
		border-color: #a3a3a3;
		color: #000000;
	}

	.circular--square {
		border-radius: 50%;
		/* 	  border-color: #a3a3a3; */
		/* 	  border: 1px solid #a3a3a3; */
	}

	.fade-enter-active,
	.fade-leave-active {
		transition: opacity .5s;
	}

	.fade-enter,
	.fade-leave-to

	/* .fade-leave-active below version 2.1.8 */
		{
		opacity: 0;
	}

	.rfc {
		font-size: x-small;
	}
</style>
<!-- /#page-wrapper -->

<script src="/assets/admin/default/js/jquery.min.js"></script>
<script>
	$(document).ready(function() {
		//page_items_count, webpages, webpages_count, users_count, users
		new Vue({
			el: '#dashboard',
			data() {
				return {
					users_count: 0,
					users: [],
					tweened_users_count: 0,
					show_users: false,
				}
			},
			filters: {
				prettydecimal(value, positions = 2) {
					return parseFloat(value).toFixed(positions).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")
				},
				percentwidth(object) {
					var val = parseFloat(object.liters) / parseFloat(object.max_liters) * 100;
					// 			  var val = parseFloat(object.liters) / parseFloat(object.liters) * 100;
					return "width: " + val.toFixed(2) + "%";
				},
				user_image_url(user) {

					var url = null;
					if (user.image_name == null) {
						let fc = user.first_name.charAt(0);
						let lc = user.last_name.charAt(0);
						url = `https://dummyimage.com/128x128/b8b8b8/ffffff.jpg&text=+${fc}${lc}+`;
					} else {
						let base_url = window.location.hostname;
						//url = `//${base_url}/media/user/image/${user.user_id}/${user.image_name}`;
						url = `//${base_url}/media/user/image/profile/${user.user_id}/${user.image_name}`;
					}
					return url;
				},
				user_edit_url(user) {
					let base_url = window.location.hostname;
					return `//${base_url}/admin/sysusers/edit/${user.user_id}`;
				},
				user_short(name) {
					var sname = name.split(' ')[0];

					if (sname.length >= 10)
						sname = sname.slice(0, 8) + '..';
					return sname;
				},
				insurance_image_url(insurance) {

					var url = null;
					if (insurance.image_name == null) {
						var sb = null;
						if (insurance.rfc != null)
							sb = insurance.rfc.slice(0, 3);
						else if (insurance.friendly_name != null)
							sb = insurance.friendly_name.slice(0, 3);
						else
							sb = 'CLP';

						url = `https://dummyimage.com/128x128/b8b8b8/ffffff.jpg&text=+${sb}+`;
					} else {
						let base_url = window.location.hostname;
						url = `//${base_url}/media/insurances/${insurance.id}/${insurance.image_name}`;
					}
					return url;
				},
				insurance_edit_url(insurance) {
					let base_url = window.location.hostname;
					return `//${base_url}/admin/insurances/edit/${insurance.id}`;
				},
				insurance_short(insurance) {

					var cname = '';
					var cna = null;
					if (insurance.friendly_name != null) {
						cna = insurance.friendly_name.split(' ');
					} else if (insurance.legal_name != null) {
						cna = insurance.legal_name.split(' ');
					} else if (insurance.rfc != null) {
						cname = insurance.rfc;
					}
					if (cna.length == 1)
						cname = cna[0];

					if (cna.length > 1)
						cname = cna[0] + ' ' + cna[1];

					if (cname.length >= 15)
						cname = cname.slice(0, 12) + '..';
					return cname;
				},
			},
			computed: {
				animated_users_count: function() {
					return this.tweened_users_count.toFixed(2);
				},
			},
			watch: {
				users_count: function(newValue) {
					gsap.to(this.$data, {
						duration: 1,
						tweened_users_count: newValue
					});
				},
			},
			mounted() {
				//when mounted start polling
				this.dashboard_poll();
				// this.dashboard_cpu_poll();

				//poll every 5 second

				// setInterval(function() {
				// 	this.dashboard_poll();
				// }.bind(this), 60000);

				/*
					setInterval(function () {
							this.dashboard_cpu_poll();
							}.bind(this), 1000);
				*/

			},
			methods: {
				//methods for this vue app
				dashboard_poll: function() {
					//use axios for the request, and mount data into global variables for the app
					axios
						.get('<?= base_url("admin/dashboard_admin_json") ?>')
						.then(response => (
							this.users_count = response.data.response.users_count,
							this.users = response.data.response.users
						))
				}
			}
		})
	});
</script>