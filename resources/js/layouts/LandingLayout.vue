<template>
    <div>
        <v-app-bar class="d-flex" flat height="80">
            <a class="skip-nav-link" href="#main-content">
                skip navigation
            </a>
            <v-container class="py-0 px-0 px-sm-2 fill-height">
                <router-link to="/dashboard" class="d-flex align-center text-decoration-none mr-2">
                    <img :src="logoimg" alt="Logo of FellowShip" height="70"/>
                </router-link>

                <v-spacer></v-spacer>


                <div class="d-none d-md-block">
                    <v-btn class="mx-1" @click="$helpers.scrollTo('#feature1');">
                        Feature 1
                    </v-btn>
                    <template v-if="!authenticated">
                        <v-btn class="mx-1" to="/auth/signin">
                            Sign In
                        </v-btn>

                        <v-btn variant="outlined" size="large" to="/auth/signup">
                            Sign Up
                        </v-btn>
                    </template>

                    <template v-else>
                        <v-btn><toolbar-notifications/></v-btn>
                        <v-btn>   <toolbar-user/></v-btn>






<!--                        <v-btn class="mx-1" @click.prevent="signOut">-->
<!--                            Sign Out-->
<!--                        </v-btn>-->


                    </template>

                </div>



            </v-container>
        </v-app-bar>

        <v-main id="main-content">
            <router-view :key="$route.fullPath"></router-view>
            <v-container></v-container>
            <v-footer color="transparent">
                <v-container class="py-5">
                    <v-row>
                        <v-col cols="12" md="4">
                            <div class="text-h6 text-lg-h5 font-weight-bold">Navigation</div>
                            <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"/>
                            <div class="d-flex flex-wrap">
                                <div v-for="(link, i) in links" :key="i" class="w-half body-1 mb-1">
                                    <router-link
                                        v-if="link.to"
                                        class="text-decoration-none text-primary"
                                        :to="link.to"
                                    >{{ link.label }}
                                    </router-link>
                                    <a
                                        v-else
                                        class="text-decoration-none text-primary"
                                        :href="link.href"
                                        :target="link.target || 'blank'"
                                    >{{ link.label }}</a>
                                </div>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-h6 text-lg-h5 font-weight-bold">Contact Information</div>
                            <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"/>
                            <div class="d-flex mb-2 font-weight-bold">
                                <v-icon color="primary lighten-1" class="mr-2">mdi-map-marker-outline</v-icon>
                                Musterstraße 11, 00000 Stadt, Germany
                            </div>
                            <div class="d-flex mb-2">
                                <v-icon color="primary lighten-1" class="mr-2">mdi-phone-outline</v-icon>
                                <a href="#" class="text-decoration-none text-primary">+0x 000 23 00 555 55</a>
                            </div>
                            <div class="d-flex mb-2">
                                <v-icon color="primary lighten-1" class="mr-2">mdi-email-outline</v-icon>
                                <a href="#" class="text-decoration-none text-primary">hello@loremcommunity.com</a>
                            </div>
                        </v-col>
                        <v-col cols="12" md="4">
                            <div class="text-h6 text-lg-h5 font-weight-bold">Newsletter</div>
                            <div style="width: 80px; height: 2px" class="mb-5 mt-1 bg-primary"/>
                            <div class="d-flex flex-column flex-lg-row w-full">
                                <v-text-field
                                    variant="outlined"
                                    label="Your email"
                                    dense
                                    height="44"
                                    class="mr-lg-2"
                                ></v-text-field>
                                <v-btn size="large" color="primary">Subscribe</v-btn>
                            </div>
                            <div class="text-center text-md-right mt-4 mt-lg-2">
                                Connect
                                <v-btn fab small color="primary" class="ml-2">
                                    <v-icon>mdi-twitter</v-icon>
                                </v-btn>
                                <v-btn fab small color="primary" class="ml-2">
                                    <v-icon>mdi-facebook</v-icon>
                                </v-btn>
                                <v-btn fab small color="primary" class="ml-2">
                                    <v-icon>mdi-instagram</v-icon>
                                </v-btn>
                            </div>
                        </v-col>
                    </v-row>
                    <v-divider class="my-3"></v-divider>
                    <div class="text-center caption">
                        © Fellowship 2021
                    </div>
                </v-container>
            </v-footer>
        </v-main>
    </div>
</template>

<script>
import config from '../configs'
import logoimg from '@/assets/images/logo.png';
import {useAuthStore} from "@/store/authStore.js";
import {useUserStore} from "@/store/userStore.js";
import ToolbarUser from '../components/toolbar/ToolbarUser.vue'
import ToolbarApps from '../components/toolbar/ToolbarApps.vue'
import ToolbarLanguage from '../components/toolbar/ToolbarLanguage.vue'
import ToolbarNotifications from '../components/toolbar/ToolbarNotifications.vue'

// import {mapActions, mapGetters} from 'vuex'

export default {
    components: {
        ToolbarUser,
        ToolbarNotifications
    },
    data() {
        return {
            logoimg,
            config,
            links: [{
                label: 'Overview',
                to: '#'
            }, {
                label: 'Features',
                to: '#'
            }, {
                label: 'Documentation',
                to: '#'
            }, {
                label: 'News',
                to: '#'
            }, {
                label: 'FAQ',
                to: '#'
            }, {
                label: 'About us',
                to: '#'
            }, {
                label: 'Carrers',
                to: '#'
            }, {
                label: 'Press',
                to: '#'
            }]
        }
    },
    methods: {

        async signOut() {
            const auth = useAuthStore()
            await auth.logout()
        }
    },
    computed: {
        authenticated() {
            const authStore = useAuthStore();
            console.log('landingauthstore',authStore)
            return authStore.isLoggedIn ;
        },
        user() {
            const userStore = useUserStore();
            return userStore.user;
        },
    },

    mounted() {
      console.log('test',this.authenticated)
    }
}
</script>

<style scoped>
/*skip to main content*/
.skip-nav-link {
    transform: translateY(-200%);
    transition: transform 325ms ease-in;
}

.skip-nav-link:focus {
    transform: translateY(-60%);
}
</style>
