<template>
  <b-container class="p-0" fluid>
    <div class="iq-maintenance text-center">
      <img class="img-fluid mb-4" src="@/assets/images/error/01.png" alt="maintenance" loading="lazy" />
      <div class="maintenance-bottom text-white pb-0">
        <div class="bg-primary" style="background: transparent; height: 320px">
          <div class="gradient-bottom">
            <div class="bottom-text general-zindex">
              <h1 class="mb-2 text-white">Hang on! We are under maintenance</h1>
              <p>It will not take a long time till we get the error fiked. We wii live again in</p>
              <ul class="countdown d-flex justify-content-center align-items-center list-inline" data-date="May 30 2025 20:20:22" ref="element">
                <li>
                  <span data-days>0</span>
                  Days
                </li>
                <li>
                  <span data-hours>0</span>
                  Hours
                </li>
                <li>
                  <span data-minutes>0</span>
                  Minutes
                </li>
                <li>
                  <span data-seconds>0</span>
                  Seconds
                </li>
              </ul>
              <div class="w-50 mx-auto mt-2">
                <div class="input-group search-input search-input">
                  <input class="form-control" type="text" placeholder="Enter your mail" />
                  <a class="btn bg-white text-primary ms-2 rounded" href="#">Notify Me</a>
                </div>
              </div>
            </div>
            <div class="c xl-circle">
              <div class="c lg-circle">
                <div class="c md-circle">
                  <div class="c sm-circle">
                    <div class="c xs-circle"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="sign-bg">
      <svg width="280" height="230" viewBox="0 0 431 398" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g opacity="0.05">
          <rect x="-157.085" y="193.773" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 -157.085 193.773)" fill="#3B8AFF" />
          <rect x="7.46875" y="358.327" width="543" height="77.5714" rx="38.7857" transform="rotate(-45 7.46875 358.327)" fill="#3B8AFF" />
          <rect x="61.9355" y="138.545" width="310.286" height="77.5714" rx="38.7857" transform="rotate(45 61.9355 138.545)" fill="#3B8AFF" />
          <rect x="62.3154" y="-190.173" width="543" height="77.5714" rx="38.7857" transform="rotate(45 62.3154 -190.173)" fill="#3B8AFF" />
        </g>
      </svg>
    </div>
  </b-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useCountdown } from '@/common/utilities/countdown'
const element = ref(null)
onMounted(() => useCountdown(element.value))
</script>

<style lang="scss" scoped></style>
