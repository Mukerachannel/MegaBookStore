// Mobile Menu Toggle
const mobileMenu = document.getElementById("mobile-menu")
const navMenu = document.querySelector(".nav-menu")

if (mobileMenu) {
  mobileMenu.addEventListener("click", () => {
    mobileMenu.classList.toggle("active")
    navMenu.classList.toggle("active")
  })
}

// Close mobile menu when clicking on a nav link
const navLinks = document.querySelectorAll(".nav-link")
navLinks.forEach((link) => {
  link.addEventListener("click", () => {
    mobileMenu.classList.remove("active")
    navMenu.classList.remove("active")
  })
})

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault()

    const targetId = this.getAttribute("href")
    if (targetId === "#") return

    const targetElement = document.querySelector(targetId)
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 80,
        behavior: "smooth",
      })
    }
  })
})



// Navbar scroll effect
window.addEventListener("scroll", () => {
  const navbar = document.querySelector(".navbar")
  if (window.scrollY > 50) {
    navbar.style.boxShadow = "0 2px 10px rgba(0, 0, 0, 0.1)"
    navbar.style.background = "rgba(255, 255, 255, 0.95)"
  } else {
    navbar.style.boxShadow = "0 2px 10px rgba(0, 0, 0, 0.1)"
    navbar.style.background = "white"
  }
})

// Simple Hero Slider - Auto Scroll Horizontally
document.addEventListener("DOMContentLoaded", () => {
  const slider = document.querySelector(".hero-slider")
  const dots = document.querySelectorAll(".hero-dot")
  let currentSlide = 0
  let slideInterval

  // Function to go to a specific slide
  function goToSlide(slideIndex) {
    currentSlide = slideIndex
    slider.style.transform = `translateX(-${currentSlide * 33.33}%)`

    // Update active dot
    dots.forEach((dot) => dot.classList.remove("active"))
    dots[currentSlide].classList.add("active")
  }

  // Function to go to the next slide
  function nextSlide() {
    currentSlide = (currentSlide + 1) % 3
    goToSlide(currentSlide)
  }

  // Start automatic sliding
  function startSlideInterval() {
    slideInterval = setInterval(nextSlide, 5000)
  }

  // Stop automatic sliding
  function stopSlideInterval() {
    clearInterval(slideInterval)
  }

  // Add click event to dots
  dots.forEach((dot, index) => {
    dot.addEventListener("click", () => {
      goToSlide(index)
      stopSlideInterval()
      startSlideInterval()
    })
  })

  // Start the slider
  startSlideInterval()

  // Pause on hover
  slider.addEventListener("mouseenter", stopSlideInterval)
  slider.addEventListener("mouseleave", startSlideInterval)
})

