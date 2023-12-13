"""Here we need to swap all the negative integers in to on side, To solve this problem we can use
two pointers approach"""

def make_negative_once_sided(arr):
    l = 0 
    r = len(arr) -1 
    while l <= r:
        if arr[l] > 0 :
            arr[l], arr[r] = arr[r] , arr[l]
            r -= 1 
        else :
            l += 1
    return arr 
print(make_negative_once_sided([-11, 2, 3, 4, -12, -13, -14, -15]))