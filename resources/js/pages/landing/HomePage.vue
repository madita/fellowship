<template>
    <div>
        <v-sheet>
            <v-container class="py-6 pt-lg-15 text-center">
                <h1 class="text-h4 text-sm-h3 text-md-h2 text-lg-h1">Your awesome <span
                    class="primary--text">community</span></h1>
                <h2 class="text-h6 text-sm-h5 mt-4 w-full w-md-8-12 w-xl-half mx-auto">Lorem ipsum dolor sit, amet
                    consectetur adipisicing elit. Commodi ex facilis ad atque natus tenetur debitis qui quisquam iure
                    amet.</h2>
                <div class="mt-8">
                    <v-btn to="/auth/signup" x-large class="my-1 mx-sm-1 w-full w-sm-auto" color="primary">Join Now!</v-btn>
                    <v-btn x-large class="my-1 mx-sm-1 w-full w-sm-auto">Learn more</v-btn>
                </div>
            </v-container>
        </v-sheet>

        <Partners/>

        <Stats/>

        <v-container>
            <v-divider></v-divider>
        </v-container>

        <Feature2/>

        <Feature1/>

        <CallToAction/>
    </div>
</template>

<script>
import Partners from '@/components/landing/Partners.vue'
import Stats from '@/components/landing/Stats.vue'
import Feature1 from '@/components/landing/Feature1.vue'
import CallToAction from '@/components/landing/CallToAction.vue'
import Feature2 from '@/components/landing/Feature2.vue'

export default {
    components: {
        Partners,
        Stats,
        Feature1,
        Feature2,
        CallToAction
    }
}
</script>
